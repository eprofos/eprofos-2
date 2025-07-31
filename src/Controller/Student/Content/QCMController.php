<?php

declare(strict_types=1);

namespace App\Controller\Student\Content;

use App\Entity\Training\QCM;
use App\Entity\User\Student;
use App\Repository\Training\QCMRepository;
use App\Service\Security\ContentAccessService;
use App\Service\Student\QCMAttemptService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Student QCM Controller.
 *
 * Handles QCM content access and interaction for enrolled students
 * with proper access control and security checks.
 */
#[Route('/student/qcm')]
#[IsGranted('ROLE_STUDENT')]
class QCMController extends AbstractController
{
    public function __construct(
        private readonly QCMRepository $qcmRepository,
        private readonly ContentAccessService $contentAccessService,
        private readonly QCMAttemptService $attemptService
    ) {
    }

    /**
     * View a specific QCM with access control.
     */
    #[Route('/{id}', name: 'student_qcm_view', methods: ['GET'])]
    #[IsGranted('view', subject: 'qcm')]
    public function view(QCM $qcm): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        // Get student's enrollment for the formation containing this QCM
        $enrollments = $this->contentAccessService->getStudentEnrollments($student);
        $enrollment = null;
        
        foreach ($enrollments as $e) {
            if ($e->getFormation() && $e->getFormation()->getId() === $qcm->getCourse()?->getChapter()?->getModule()?->getFormation()?->getId()) {
                $enrollment = $e;
                break;
            }
        }

        // Get attempt information
        $attempts = $this->attemptService->getStudentAttempts($student, $qcm);
        $bestScore = $this->attemptService->getStudentBestScore($student, $qcm);
        $hasPassed = $this->attemptService->hasStudentPassed($student, $qcm);
        $canAttempt = $this->attemptService->canStudentAttempt($student, $qcm);
        $remainingAttempts = $this->attemptService->getRemainingAttempts($student, $qcm);
        $activeAttempt = $this->attemptService->getActiveAttempt($student, $qcm);

        return $this->render('student/content/qcm/view.html.twig', [
            'qcm' => $qcm,
            'course' => $qcm->getCourse(),
            'chapter' => $qcm->getCourse()?->getChapter(),
            'module' => $qcm->getCourse()?->getChapter()?->getModule(),
            'formation' => $qcm->getCourse()?->getChapter()?->getModule()?->getFormation(),
            'enrollment' => $enrollment,
            'student' => $student,
            'attempts' => $attempts,
            'best_score' => $bestScore,
            'has_passed' => $hasPassed,
            'can_attempt' => $canAttempt,
            'remaining_attempts' => $remainingAttempts,
            'active_attempt' => $activeAttempt,
            'page_title' => $qcm->getTitle(),
        ]);
    }

    /**
     * Take/interact with a QCM.
     */
    #[Route('/{id}/take', name: 'student_qcm_take', methods: ['GET', 'POST'])]
    #[IsGranted('interact', subject: 'qcm')]
    public function take(QCM $qcm, Request $request): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        if (!$this->attemptService->canStudentAttempt($student, $qcm)) {
            $this->addFlash('error', 'Vous ne pouvez plus commencer ce QCM.');
            return $this->redirectToRoute('student_qcm_view', ['id' => $qcm->getId()]);
        }

        // Get or create active attempt
        $attempt = $this->attemptService->getOrCreateActiveAttempt($student, $qcm);

        // Get randomized questions
        $questions = $this->attemptService->getRandomizedQuestions($qcm);

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');
            
            if ($action === 'submit') {
                try {
                    // Save final answers
                    $questionData = $request->request->all();
                    
                    // Process answers for each question by index
                    foreach ($questions as $questionIndex => $question) {
                        $answerKey = 'question_' . $questionIndex;
                        if (isset($questionData[$answerKey])) {
                            $answerValue = $questionData[$answerKey];
                            // Convert single answer to array for consistency
                            $answerIndices = is_array($answerValue) ? $answerValue : [$answerValue];
                            $this->attemptService->saveAnswer($attempt, $questionIndex, $answerIndices);
                        }
                    }
                    
                    $this->attemptService->submitAttempt($attempt);
                    $this->addFlash('success', 'QCM soumis avec succès!');
                    
                    return $this->redirectToRoute('student_qcm_result', [
                        'id' => $qcm->getId(),
                        'attemptId' => $attempt->getId()
                    ]);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur lors de la soumission: ' . $e->getMessage());
                }
            } elseif ($action === 'abandon') {
                try {
                    $this->attemptService->abandonAttempt($attempt);
                    $this->addFlash('info', 'QCM abandonné.');
                    return $this->redirectToRoute('student_qcm_view', ['id' => $qcm->getId()]);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur lors de l\'abandon: ' . $e->getMessage());
                }
            }
        }

        // Get current answers and calculate progress
        $answers = $attempt->getAnswers() ?? [];
        $answeredQuestions = [];
        $answeredCount = 0;

        // Build array of answered question indices and format answers for template
        foreach ($questions as $index => $question) {
            $questionAnswers = $attempt->getAnswerForQuestion($index);
            if (!empty($questionAnswers)) {
                $answeredQuestions[] = $index;
                $answeredCount++;
            }
        }

        return $this->render('student/content/qcm/take.html.twig', [
            'qcm' => $qcm,
            'attempt' => $attempt,
            'questions' => $questions,
            'student' => $student,
            'answers' => $answers,
            'answered_questions' => $answeredQuestions,
            'answered_count' => $answeredCount,
            'page_title' => 'QCM: ' . $qcm->getTitle(),
        ]);
    }

    /**
     * Save answer via AJAX.
     */
    #[Route('/{id}/save-answer', name: 'student_qcm_save_answer', methods: ['POST'])]
    #[IsGranted('interact', subject: 'qcm')]
    public function saveAnswer(QCM $qcm, Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $this->getUser();

        try {
            $activeAttempt = $this->attemptService->getActiveAttempt($student, $qcm);
            
            if (!$activeAttempt) {
                return new JsonResponse(['success' => false, 'message' => 'Aucune tentative active'], 400);
            }

            // Get all form data and process answers for each question
            $questionData = $request->request->all();
            $questions = $this->attemptService->getRandomizedQuestions($qcm);
            
            foreach ($questions as $questionIndex => $question) {
                $answerKey = 'question_' . $questionIndex;
                if (isset($questionData[$answerKey])) {
                    $answerValue = $questionData[$answerKey];
                    // Convert single answer to array for consistency
                    $answerIndices = is_array($answerValue) ? $answerValue : [$answerValue];
                    $this->attemptService->saveAnswer($activeAttempt, $questionIndex, $answerIndices);
                }
            }
            
            return new JsonResponse(['success' => true, 'message' => 'Réponse sauvegardée']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * Get remaining time for active attempt.
     */
    #[Route('/{id}/remaining-time', name: 'student_qcm_remaining_time', methods: ['GET'])]
    #[IsGranted('interact', subject: 'qcm')]
    public function getRemainingTime(QCM $qcm): JsonResponse
    {
        /** @var Student $student */
        $student = $this->getUser();

        $activeAttempt = $this->attemptService->getActiveAttempt($student, $qcm);
        
        if (!$activeAttempt) {
            return new JsonResponse(['success' => false, 'message' => 'Aucune tentative active']);
        }

        $remainingSeconds = $activeAttempt->getRemainingTimeSeconds();
        
        return new JsonResponse([
            'success' => true,
            'remaining_seconds' => $remainingSeconds,
            'expired' => $remainingSeconds === 0
        ]);
    }

    /**
     * View QCM results.
     */
    #[Route('/{id}/result/{attemptId}', name: 'student_qcm_result', methods: ['GET'])]
    #[IsGranted('view', subject: 'qcm')]
    public function result(QCM $qcm, int $attemptId): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        $attempts = $this->attemptService->getStudentAttempts($student, $qcm);
        $attempt = null;
        
        foreach ($attempts as $a) {
            if ($a->getId() === $attemptId) {
                $attempt = $a;
                break;
            }
        }

        if (!$attempt) {
            throw $this->createNotFoundException('Attempt not found');
        }

        // Show results only if QCM is configured to show them
        $showCorrectAnswers = $qcm->isShowCorrectAnswers();
        $showExplanations = $qcm->isShowExplanations();

        return $this->render('student/content/qcm/result.html.twig', [
            'qcm' => $qcm,
            'attempt' => $attempt,
            'student' => $student,
            'show_correct_answers' => $showCorrectAnswers,
            'show_explanations' => $showExplanations,
            'page_title' => 'Résultat: ' . $qcm->getTitle(),
        ]);
    }

    /**
     * Retry a QCM (start new attempt).
     */
    #[Route('/{id}/retry', name: 'student_qcm_retry', methods: ['POST'])]
    #[IsGranted('interact', subject: 'qcm')]
    public function retry(QCM $qcm): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        if (!$this->attemptService->canStudentAttempt($student, $qcm)) {
            $this->addFlash('error', 'Vous avez atteint le nombre maximum de tentatives pour ce QCM.');
            return $this->redirectToRoute('student_qcm_view', ['id' => $qcm->getId()]);
        }

        return $this->redirectToRoute('student_qcm_take', ['id' => $qcm->getId()]);
    }
}
