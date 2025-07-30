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
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Check if student can access a formation.
     */
    public function canAccessFormation(Student $student, Formation $formation): bool
    {
        return $this->hasActiveEnrollmentForFormation($student, $formation);
    }

    /**
     * Check if student can access a module.
     */
    public function canAccessModule(Student $student, Module $module): bool
    {
        $formation = $module->getFormation();
        if (!$formation) {
            return false;
        }

        return $this->canAccessFormation($student, $formation);
    }

    /**
     * Check if student can access a chapter.
     */
    public function canAccessChapter(Student $student, Chapter $chapter): bool
    {
        $module = $chapter->getModule();
        if (!$module) {
            return false;
        }

        return $this->canAccessModule($student, $module);
    }

    /**
     * Check if student can access a course.
     */
    public function canAccessCourse(Student $student, Course $course): bool
    {
        $chapter = $course->getChapter();
        if (!$chapter) {
            return false;
        }

        return $this->canAccessChapter($student, $chapter);
    }

    /**
     * Check if student can access an exercise.
     */
    public function canAccessExercise(Student $student, Exercise $exercise): bool
    {
        $course = $exercise->getCourse();
        if (!$course) {
            return false;
        }

        return $this->canAccessCourse($student, $course);
    }

    /**
     * Check if student can access a QCM.
     */
    public function canAccessQCM(Student $student, QCM $qcm): bool
    {
        $course = $qcm->getCourse();
        if (!$course) {
            return false;
        }

        return $this->canAccessCourse($student, $course);
    }

    /**
     * Get all student enrollments.
     *
     * @return StudentEnrollment[]
     */
    public function getStudentEnrollments(Student $student): array
    {
        return $this->enrollmentRepository->findActiveEnrollmentsByStudent($student);
    }

    /**
     * Get all formations accessible to a student.
     *
     * @return Formation[]
     */
    public function getAccessibleFormations(Student $student): array
    {
        $enrollments = $this->getStudentEnrollments($student);
        $formations = [];

        foreach ($enrollments as $enrollment) {
            $formation = $enrollment->getFormation();
            if ($formation && !in_array($formation, $formations, true)) {
                $formations[] = $formation;
            }
        }

        return $formations;
    }

    /**
     * Log content access attempt for Qualiopi compliance.
     */
    public function logContentAccess(Student $student, object $content, bool $granted): void
    {
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
            'timestamp' => new \DateTimeImmutable(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ];

        if ($granted) {
            $this->logger->info('Content access granted', $logContext);
        } else {
            $this->logger->warning('Content access denied', $logContext);
        }

        // Store access log in database for Qualiopi compliance
        $this->storeAccessLog($logContext);
    }

    /**
     * Check if student has active enrollment for formation.
     */
    private function hasActiveEnrollmentForFormation(Student $student, Formation $formation): bool
    {
        return $this->enrollmentRepository->hasStudentAccessToFormation($student, $formation);
    }

    /**
     * Get content type string for logging.
     */
    private function getContentType(object $content): string
    {
        return match (true) {
            $content instanceof Formation => 'formation',
            $content instanceof Module => 'module',
            $content instanceof Chapter => 'chapter',
            $content instanceof Course => 'course',
            $content instanceof Exercise => 'exercise',
            $content instanceof QCM => 'qcm',
            default => 'unknown'
        };
    }

    /**
     * Store access log in database for Qualiopi compliance tracking.
     */
    private function storeAccessLog(array $logData): void
    {
        // Note: For now we're logging to files. In a future enhancement,
        // we could create a ContentAccessLog entity to store this in the database
        // for more advanced reporting and Qualiopi documentation.
        
        // For now, the file logging in logContentAccess() is sufficient
        // but this method provides a hook for future database logging
    }
}
