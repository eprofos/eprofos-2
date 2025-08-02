<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\Core\StudentEnrollment;
use App\Entity\Training\Chapter;
use App\Entity\Training\Course;
use App\Entity\Training\Exercise;
use App\Entity\Training\Formation;
use App\Entity\Training\Module;
use App\Entity\Training\QCM;
use App\Entity\User\Student;
use App\Repository\Core\StudentEnrollmentRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * ContentAccessService handles access control for training content.
 *
 * This service is the core of the Student Content Access System, providing
 * enrollment-based access control for all levels of training content.
 * Essential for Qualiopi compliance and security.
 */
class ContentAccessService
{
    public function __construct(
        private readonly StudentEnrollmentRepository $enrollmentRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Check if student can access a formation.
     */
    public function canAccessFormation(Student $student, Formation $formation): bool
    {
        try {
            $this->logger->info('Checking formation access', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'formation_id' => $formation->getId(),
                'formation_title' => $formation->getTitle(),
                'formation_slug' => $formation->getSlug(),
                'method' => __METHOD__,
            ]);

            $hasAccess = $this->hasActiveEnrollmentForFormation($student, $formation);

            $this->logger->info('Formation access check completed', [
                'student_id' => $student->getId(),
                'formation_id' => $formation->getId(),
                'access_granted' => $hasAccess,
                'method' => __METHOD__,
            ]);

            $this->logContentAccess($student, $formation, $hasAccess);

            return $hasAccess;
        } catch (Exception $e) {
            $this->logger->error('Error checking formation access', [
                'student_id' => $student->getId(),
                'formation_id' => $formation->getId(),
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            // Return false for security - deny access on error
            return false;
        }
    }

    /**
     * Check if student can access a module.
     */
    public function canAccessModule(Student $student, Module $module): bool
    {
        try {
            $this->logger->info('Checking module access', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'module_id' => $module->getId(),
                'module_title' => $module->getTitle(),
                'formation_id' => $module->getFormation()?->getId(),
                'formation_title' => $module->getFormation()?->getTitle(),
                'method' => __METHOD__,
            ]);

            $formation = $module->getFormation();
            if (!$formation) {
                $this->logger->warning('Module has no associated formation', [
                    'student_id' => $student->getId(),
                    'module_id' => $module->getId(),
                    'module_title' => $module->getTitle(),
                    'method' => __METHOD__,
                ]);

                return false;
            }

            $hasAccess = $this->canAccessFormation($student, $formation);

            $this->logger->info('Module access check completed', [
                'student_id' => $student->getId(),
                'module_id' => $module->getId(),
                'formation_id' => $formation->getId(),
                'access_granted' => $hasAccess,
                'method' => __METHOD__,
            ]);

            $this->logContentAccess($student, $module, $hasAccess);

            return $hasAccess;
        } catch (Exception $e) {
            $this->logger->error('Error checking module access', [
                'student_id' => $student->getId(),
                'module_id' => $module->getId(),
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            // Return false for security - deny access on error
            return false;
        }
    }

    /**
     * Check if student can access a chapter.
     */
    public function canAccessChapter(Student $student, Chapter $chapter): bool
    {
        try {
            $this->logger->info('Checking chapter access', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'chapter_id' => $chapter->getId(),
                'chapter_title' => $chapter->getTitle(),
                'module_id' => $chapter->getModule()?->getId(),
                'module_title' => $chapter->getModule()?->getTitle(),
                'formation_id' => $chapter->getModule()?->getFormation()?->getId(),
                'formation_title' => $chapter->getModule()?->getFormation()?->getTitle(),
                'method' => __METHOD__,
            ]);

            $module = $chapter->getModule();
            if (!$module) {
                $this->logger->warning('Chapter has no associated module', [
                    'student_id' => $student->getId(),
                    'chapter_id' => $chapter->getId(),
                    'chapter_title' => $chapter->getTitle(),
                    'method' => __METHOD__,
                ]);

                return false;
            }

            $hasAccess = $this->canAccessModule($student, $module);

            $this->logger->info('Chapter access check completed', [
                'student_id' => $student->getId(),
                'chapter_id' => $chapter->getId(),
                'module_id' => $module->getId(),
                'access_granted' => $hasAccess,
                'method' => __METHOD__,
            ]);

            $this->logContentAccess($student, $chapter, $hasAccess);

            return $hasAccess;
        } catch (Exception $e) {
            $this->logger->error('Error checking chapter access', [
                'student_id' => $student->getId(),
                'chapter_id' => $chapter->getId(),
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            // Return false for security - deny access on error
            return false;
        }
    }

    /**
     * Check if student can access a course.
     */
    public function canAccessCourse(Student $student, Course $course): bool
    {
        try {
            $this->logger->info('Checking course access', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'course_id' => $course->getId(),
                'course_title' => $course->getTitle(),
                'chapter_id' => $course->getChapter()?->getId(),
                'chapter_title' => $course->getChapter()?->getTitle(),
                'module_id' => $course->getChapter()?->getModule()?->getId(),
                'module_title' => $course->getChapter()?->getModule()?->getTitle(),
                'formation_id' => $course->getChapter()?->getModule()?->getFormation()?->getId(),
                'formation_title' => $course->getChapter()?->getModule()?->getFormation()?->getTitle(),
                'method' => __METHOD__,
            ]);

            $chapter = $course->getChapter();
            if (!$chapter) {
                $this->logger->warning('Course has no associated chapter', [
                    'student_id' => $student->getId(),
                    'course_id' => $course->getId(),
                    'course_title' => $course->getTitle(),
                    'method' => __METHOD__,
                ]);

                return false;
            }

            $hasAccess = $this->canAccessChapter($student, $chapter);

            $this->logger->info('Course access check completed', [
                'student_id' => $student->getId(),
                'course_id' => $course->getId(),
                'chapter_id' => $chapter->getId(),
                'access_granted' => $hasAccess,
                'method' => __METHOD__,
            ]);

            $this->logContentAccess($student, $course, $hasAccess);

            return $hasAccess;
        } catch (Exception $e) {
            $this->logger->error('Error checking course access', [
                'student_id' => $student->getId(),
                'course_id' => $course->getId(),
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            // Return false for security - deny access on error
            return false;
        }
    }

    /**
     * Check if student can access an exercise.
     */
    public function canAccessExercise(Student $student, Exercise $exercise): bool
    {
        try {
            $this->logger->info('Checking exercise access', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'exercise_id' => $exercise->getId(),
                'exercise_title' => $exercise->getTitle(),
                'exercise_type' => $exercise->getType(),
                'course_id' => $exercise->getCourse()?->getId(),
                'course_title' => $exercise->getCourse()?->getTitle(),
                'chapter_id' => $exercise->getCourse()?->getChapter()?->getId(),
                'chapter_title' => $exercise->getCourse()?->getChapter()?->getTitle(),
                'method' => __METHOD__,
            ]);

            $course = $exercise->getCourse();
            if (!$course) {
                $this->logger->warning('Exercise has no associated course', [
                    'student_id' => $student->getId(),
                    'exercise_id' => $exercise->getId(),
                    'exercise_title' => $exercise->getTitle(),
                    'method' => __METHOD__,
                ]);

                return false;
            }

            $hasAccess = $this->canAccessCourse($student, $course);

            $this->logger->info('Exercise access check completed', [
                'student_id' => $student->getId(),
                'exercise_id' => $exercise->getId(),
                'course_id' => $course->getId(),
                'access_granted' => $hasAccess,
                'method' => __METHOD__,
            ]);

            $this->logContentAccess($student, $exercise, $hasAccess);

            return $hasAccess;
        } catch (Exception $e) {
            $this->logger->error('Error checking exercise access', [
                'student_id' => $student->getId(),
                'exercise_id' => $exercise->getId(),
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            // Return false for security - deny access on error
            return false;
        }
    }

    /**
     * Check if student can access a QCM.
     */
    public function canAccessQCM(Student $student, QCM $qcm): bool
    {
        try {
            $this->logger->info('Checking QCM access', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'qcm_id' => $qcm->getId(),
                'qcm_title' => $qcm->getTitle(),
                'course_id' => $qcm->getCourse()?->getId(),
                'course_title' => $qcm->getCourse()?->getTitle(),
                'chapter_id' => $qcm->getCourse()?->getChapter()?->getId(),
                'chapter_title' => $qcm->getCourse()?->getChapter()?->getTitle(),
                'method' => __METHOD__,
            ]);

            $course = $qcm->getCourse();
            if (!$course) {
                $this->logger->warning('QCM has no associated course', [
                    'student_id' => $student->getId(),
                    'qcm_id' => $qcm->getId(),
                    'qcm_title' => $qcm->getTitle(),
                    'method' => __METHOD__,
                ]);

                return false;
            }

            $hasAccess = $this->canAccessCourse($student, $course);

            $this->logger->info('QCM access check completed', [
                'student_id' => $student->getId(),
                'qcm_id' => $qcm->getId(),
                'course_id' => $course->getId(),
                'access_granted' => $hasAccess,
                'method' => __METHOD__,
            ]);

            $this->logContentAccess($student, $qcm, $hasAccess);

            return $hasAccess;
        } catch (Exception $e) {
            $this->logger->error('Error checking QCM access', [
                'student_id' => $student->getId(),
                'qcm_id' => $qcm->getId(),
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            // Return false for security - deny access on error
            return false;
        }
    }

    /**
     * Get all student enrollments.
     *
     * @return StudentEnrollment[]
     */
    public function getStudentEnrollments(Student $student): array
    {
        try {
            $this->logger->info('Retrieving student enrollments', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'method' => __METHOD__,
            ]);

            $enrollments = $this->enrollmentRepository->findActiveEnrollmentsByStudent($student);

            $this->logger->info('Student enrollments retrieved successfully', [
                'student_id' => $student->getId(),
                'enrollments_count' => count($enrollments),
                'enrollment_ids' => array_map(static fn ($e) => $e->getId(), $enrollments),
                'formation_ids' => array_map(static fn ($e) => $e->getFormation()?->getId(), $enrollments),
                'method' => __METHOD__,
            ]);

            return $enrollments;
        } catch (Exception $e) {
            $this->logger->error('Error retrieving student enrollments', [
                'student_id' => $student->getId(),
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            // Return empty array on error to prevent security issues
            return [];
        }
    }

    /**
     * Get all formations accessible to a student.
     *
     * @return Formation[]
     */
    public function getAccessibleFormations(Student $student): array
    {
        try {
            $this->logger->info('Retrieving accessible formations for student', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'method' => __METHOD__,
            ]);

            $enrollments = $this->getStudentEnrollments($student);
            $formations = [];

            foreach ($enrollments as $enrollment) {
                $formation = $enrollment->getFormation();
                if ($formation && !in_array($formation, $formations, true)) {
                    $formations[] = $formation;

                    $this->logger->debug('Formation added to accessible list', [
                        'student_id' => $student->getId(),
                        'formation_id' => $formation->getId(),
                        'formation_title' => $formation->getTitle(),
                        'enrollment_id' => $enrollment->getId(),
                        'method' => __METHOD__,
                    ]);
                }
            }

            $this->logger->info('Accessible formations retrieved successfully', [
                'student_id' => $student->getId(),
                'total_enrollments' => count($enrollments),
                'accessible_formations_count' => count($formations),
                'formation_ids' => array_map(static fn ($f) => $f->getId(), $formations),
                'formation_titles' => array_map(static fn ($f) => $f->getTitle(), $formations),
                'method' => __METHOD__,
            ]);

            return $formations;
        } catch (Exception $e) {
            $this->logger->error('Error retrieving accessible formations', [
                'student_id' => $student->getId(),
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            // Return empty array on error to prevent security issues
            return [];
        }
    }

    /**
     * Log content access attempt for Qualiopi compliance.
     */
    public function logContentAccess(Student $student, object $content, bool $granted): void
    {
        try {
            $contentType = $this->getContentType($content);
            $contentId = method_exists($content, 'getId') ? $content->getId() : 'unknown';
            $contentTitle = method_exists($content, 'getTitle') ? $content->getTitle() : 'unknown';

            $logContext = [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'content_type' => $contentType,
                'content_id' => $contentId,
                'content_title' => $contentTitle,
                'access_granted' => $granted,
                'timestamp' => new DateTimeImmutable(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'method' => __METHOD__,
            ];

            if ($granted) {
                $this->logger->info('Content access granted', $logContext);
            } else {
                $this->logger->warning('Content access denied', $logContext);
            }

            // Store access log in database for Qualiopi compliance
            $this->storeAccessLog($logContext);

            $this->logger->debug('Content access logging completed', [
                'student_id' => $student->getId(),
                'content_type' => $contentType,
                'content_id' => $contentId,
                'access_granted' => $granted,
                'method' => __METHOD__,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error logging content access', [
                'student_id' => $student->getId(),
                'content_type' => $this->getContentType($content),
                'content_id' => method_exists($content, 'getId') ? $content->getId() : 'unknown',
                'access_granted' => $granted,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            // Don't throw exception to prevent breaking the access check flow
            // Logging failure should not prevent normal operation
        }
    }

    /**
     * Check if student has active enrollment for formation.
     */
    private function hasActiveEnrollmentForFormation(Student $student, Formation $formation): bool
    {
        try {
            $this->logger->debug('Checking active enrollment for formation', [
                'student_id' => $student->getId(),
                'formation_id' => $formation->getId(),
                'formation_title' => $formation->getTitle(),
                'method' => __METHOD__,
            ]);

            $hasEnrollment = $this->enrollmentRepository->hasStudentAccessToFormation($student, $formation);

            $this->logger->debug('Active enrollment check completed', [
                'student_id' => $student->getId(),
                'formation_id' => $formation->getId(),
                'has_active_enrollment' => $hasEnrollment,
                'method' => __METHOD__,
            ]);

            return $hasEnrollment;
        } catch (Exception $e) {
            $this->logger->error('Error checking active enrollment for formation', [
                'student_id' => $student->getId(),
                'formation_id' => $formation->getId(),
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            // Return false for security - deny access on error
            return false;
        }
    }

    /**
     * Get content type string for logging.
     */
    private function getContentType(object $content): string
    {
        try {
            $contentType = match (true) {
                $content instanceof Formation => 'formation',
                $content instanceof Module => 'module',
                $content instanceof Chapter => 'chapter',
                $content instanceof Course => 'course',
                $content instanceof Exercise => 'exercise',
                $content instanceof QCM => 'qcm',
                default => 'unknown'
            };

            $this->logger->debug('Content type determined', [
                'content_class' => get_class($content),
                'content_type' => $contentType,
                'content_id' => method_exists($content, 'getId') ? $content->getId() : 'unknown',
                'method' => __METHOD__,
            ]);

            return $contentType;
        } catch (Exception $e) {
            $this->logger->error('Error determining content type', [
                'content_class' => get_class($content),
                'error_message' => $e->getMessage(),
                'method' => __METHOD__,
            ]);

            return 'unknown';
        }
    }

    /**
     * Store access log in database for Qualiopi compliance tracking.
     */
    private function storeAccessLog(array $logData): void
    {
        try {
            $this->logger->debug('Storing access log for Qualiopi compliance', [
                'student_id' => $logData['student_id'] ?? 'unknown',
                'content_type' => $logData['content_type'] ?? 'unknown',
                'content_id' => $logData['content_id'] ?? 'unknown',
                'access_granted' => $logData['access_granted'] ?? false,
                'log_data_keys' => array_keys($logData),
                'method' => __METHOD__,
            ]);

            // Note: For now we're logging to files. In a future enhancement,
            // we could create a ContentAccessLog entity to store this in the database
            // for more advanced reporting and Qualiopi documentation.

            // For now, the file logging in logContentAccess() is sufficient
            // but this method provides a hook for future database logging

            $this->logger->debug('Access log storage completed', [
                'student_id' => $logData['student_id'] ?? 'unknown',
                'content_type' => $logData['content_type'] ?? 'unknown',
                'content_id' => $logData['content_id'] ?? 'unknown',
                'method' => __METHOD__,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error storing access log', [
                'student_id' => $logData['student_id'] ?? 'unknown',
                'content_type' => $logData['content_type'] ?? 'unknown',
                'content_id' => $logData['content_id'] ?? 'unknown',
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'method' => __METHOD__,
            ]);

            // Don't throw exception to prevent breaking the access check flow
            // Log storage failure should not prevent normal operation
        }
    }
}
