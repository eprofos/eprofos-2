<?php

declare(strict_types=1);

namespace App\Controller\Student\Content;

use App\Entity\Training\Exercise;
use App\Entity\User\Student;
use App\Repository\Training\ExerciseRepository;
use App\Service\Security\ContentAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Student Exercise Controller.
 *
 * Handles exercise content access and interaction for enrolled students
 * with proper access control and security checks.
 */
#[Route('/student/exercise')]
#[IsGranted('ROLE_STUDENT')]
class ExerciseController extends AbstractController
{
    public function __construct(
        private readonly ExerciseRepository $exerciseRepository,
        private readonly ContentAccessService $contentAccessService
    ) {
    }

    /**
     * View a specific exercise with access control.
     */
    #[Route('/{id}', name: 'student_exercise_view', methods: ['GET'])]
    #[IsGranted('view', subject: 'exercise')]
    public function view(Exercise $exercise): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        // Get student's enrollment for the formation containing this exercise
        $enrollments = $this->contentAccessService->getStudentEnrollments($student);
        $enrollment = null;
        
        foreach ($enrollments as $e) {
            if ($e->getFormation() && $e->getFormation()->getId() === $exercise->getCourse()?->getChapter()?->getModule()?->getFormation()?->getId()) {
                $enrollment = $e;
                break;
            }
        }

        return $this->render('student/content/exercise/view.html.twig', [
            'exercise' => $exercise,
            'course' => $exercise->getCourse(),
            'chapter' => $exercise->getCourse()?->getChapter(),
            'module' => $exercise->getCourse()?->getChapter()?->getModule(),
            'formation' => $exercise->getCourse()?->getChapter()?->getModule()?->getFormation(),
            'enrollment' => $enrollment,
            'student' => $student,
            'page_title' => $exercise->getTitle(),
        ]);
    }

    /**
     * Start/interact with an exercise.
     */
    #[Route('/{id}/start', name: 'student_exercise_start', methods: ['GET', 'POST'])]
    #[IsGranted('interact', subject: 'exercise')]
    public function start(Exercise $exercise): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        // TODO: Implement exercise interaction logic
        // This would include tracking progress, saving answers, etc.

        return $this->render('student/content/exercise/start.html.twig', [
            'exercise' => $exercise,
            'student' => $student,
            'page_title' => 'Exercice: ' . $exercise->getTitle(),
        ]);
    }
}
