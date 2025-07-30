<?php

declare(strict_types=1);

namespace App\Controller\Student\Content;

use App\Entity\Training\Course;
use App\Entity\User\Student;
use App\Repository\Training\CourseRepository;
use App\Service\Security\ContentAccessService;
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
        private readonly ContentAccessService $contentAccessService
    ) {
    }

    /**
     * View a specific course with access control.
     */
    #[Route('/{id}', name: 'student_course_view', methods: ['GET'])]
    #[IsGranted('view', subject: 'course')]
    public function view(Course $course): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        // Get student's enrollment for the formation containing this course
        $enrollments = $this->contentAccessService->getStudentEnrollments($student);
        $enrollment = null;
        
        foreach ($enrollments as $e) {
            if ($e->getFormation() && $e->getFormation()->getId() === $course->getChapter()?->getModule()?->getFormation()?->getId()) {
                $enrollment = $e;
                break;
            }
        }

        return $this->render('student/content/course/view.html.twig', [
            'course' => $course,
            'chapter' => $course->getChapter(),
            'module' => $course->getChapter()?->getModule(),
            'formation' => $course->getChapter()?->getModule()?->getFormation(),
            'enrollment' => $enrollment,
            'student' => $student,
            'page_title' => $course->getTitle(),
        ]);
    }
}
