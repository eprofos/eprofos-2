<?php

declare(strict_types=1);

namespace App\Controller\Student\Content;

use App\Entity\Training\Exercise;
use App\Entity\User\Student;
use App\Repository\Training\ExerciseRepository;
use App\Service\Security\ContentAccessService;
use App\Service\Student\ExerciseSubmissionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
        private readonly ContentAccessService $contentAccessService,
        private readonly ExerciseSubmissionService $submissionService
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

        // Get submission information
        $submissions = $this->submissionService->getStudentSubmissions($student, $exercise);
        $bestScore = $this->submissionService->getStudentBestScore($student, $exercise);
        $hasPassed = $this->submissionService->hasStudentPassed($student, $exercise);
        $canAttempt = $this->submissionService->canStudentAttempt($student, $exercise);

        return $this->render('student/content/exercise/view.html.twig', [
            'exercise' => $exercise,
            'course' => $exercise->getCourse(),
            'chapter' => $exercise->getCourse()?->getChapter(),
            'module' => $exercise->getCourse()?->getChapter()?->getModule(),
            'formation' => $exercise->getCourse()?->getChapter()?->getModule()?->getFormation(),
            'enrollment' => $enrollment,
            'student' => $student,
            'submissions' => $submissions,
            'best_score' => $bestScore,
            'has_passed' => $hasPassed,
            'can_attempt' => $canAttempt,
            'page_title' => $exercise->getTitle(),
        ]);
    }

    /**
     * Start/interact with an exercise.
     */
    #[Route('/{id}/start', name: 'student_exercise_start', methods: ['GET', 'POST'])]
    #[IsGranted('interact', subject: 'exercise')]
    public function start(Exercise $exercise, Request $request): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        if (!$this->submissionService->canStudentAttempt($student, $exercise)) {
            $this->addFlash('error', 'Vous ne pouvez plus démarrer cette exercice.');
            return $this->redirectToRoute('student_exercise_view', ['id' => $exercise->getId()]);
        }

        // Get or create submission
        $submission = $this->submissionService->getOrCreateSubmission($student, $exercise);

        if ($request->isMethod('POST')) {
            $submissionData = $request->request->all();
            
            try {
                $this->submissionService->saveSubmissionData($submission, $submissionData);
                
                if ($request->request->get('action') === 'submit') {
                    $this->submissionService->submitExercise($submission);
                    $this->addFlash('success', 'Exercice soumis avec succès!');
                    return $this->redirectToRoute('student_exercise_view', ['id' => $exercise->getId()]);
                }
                
                $this->addFlash('success', 'Progression sauvegardée.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la sauvegarde: ' . $e->getMessage());
            }
        }

        return $this->render('student/content/exercise/start.html.twig', [
            'exercise' => $exercise,
            'submission' => $submission,
            'student' => $student,
            'page_title' => 'Exercice: ' . $exercise->getTitle(),
        ]);
    }

    /**
     * Auto-save exercise data via AJAX.
     */
    #[Route('/{id}/autosave', name: 'student_exercise_autosave', methods: ['POST'])]
    #[IsGranted('interact', subject: 'exercise')]
    public function autosave(Exercise $exercise, Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $this->getUser();

        try {
            $submission = $this->submissionService->getOrCreateSubmission($student, $exercise);
            $data = json_decode($request->getContent(), true);
            
            $this->submissionService->saveSubmissionData($submission, $data);
            
            return new JsonResponse(['success' => true, 'message' => 'Sauvegardé automatiquement']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Submit exercise for grading.
     */
    #[Route('/{id}/submit', name: 'student_exercise_submit', methods: ['POST'])]
    #[IsGranted('interact', subject: 'exercise')]
    public function submit(Exercise $exercise, Request $request): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        try {
            $submission = $this->submissionService->getOrCreateSubmission($student, $exercise);
            
            // Save final data
            $submissionData = $request->request->all();
            $this->submissionService->saveSubmissionData($submission, $submissionData);
            
            // Submit for grading
            $this->submissionService->submitExercise($submission);
            
            $this->addFlash('success', 'Exercice soumis avec succès pour évaluation!');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la soumission: ' . $e->getMessage());
        }

        return $this->redirectToRoute('student_exercise_view', ['id' => $exercise->getId()]);
    }

    /**
     * View exercise results.
     */
    #[Route('/{id}/result/{submissionId}', name: 'student_exercise_result', methods: ['GET'])]
    #[IsGranted('view', subject: 'exercise')]
    public function result(Exercise $exercise, int $submissionId): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        $submissions = $this->submissionService->getStudentSubmissions($student, $exercise);
        $submission = null;
        
        foreach ($submissions as $s) {
            if ($s->getId() === $submissionId) {
                $submission = $s;
                break;
            }
        }

        if (!$submission) {
            throw $this->createNotFoundException('Submission not found');
        }

        return $this->render('student/content/exercise/result.html.twig', [
            'exercise' => $exercise,
            'submission' => $submission,
            'student' => $student,
            'page_title' => 'Résultat: ' . $exercise->getTitle(),
        ]);
    }
}
