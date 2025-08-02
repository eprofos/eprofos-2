<?php

declare(strict_types=1);

namespace App\Controller\Student\Content;

use App\Entity\Training\QCM;
use App\Entity\User\Student;
use App\Repository\Training\QCMRepository;
use App\Service\Security\ContentAccessService;
use App\Service\Student\QCMAttemptService;
use DateTime;
use Exception;
use InvalidArgumentException;
use LogicException;
use Psr\Log\LoggerInterface;
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
        private readonly QCMAttemptService $attemptService,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * View a specific QCM with access control.
     */
    #[Route('/{id}', name: 'student_qcm_view', methods: ['GET'])]
    #[IsGranted('view', subject: 'qcm')]
    public function view(QCM $qcm): Response
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student attempting to view QCM', [
                'qcm_id' => $qcm->getId(),
                'qcm_title' => $qcm->getTitle(),
                'qcm_time_limit_minutes' => $qcm->getTimeLimitMinutes(),
                'qcm_max_attempts' => $qcm->getMaxAttempts(),
                'qcm_passing_score' => $qcm->getPassingScore(),
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'course_id' => $qcm->getCourse()?->getId(),
                'chapter_id' => $qcm->getCourse()?->getChapter()?->getId(),
                'module_id' => $qcm->getCourse()?->getChapter()?->getModule()?->getId(),
                'formation_id' => $qcm->getCourse()?->getChapter()?->getModule()?->getFormation()?->getId(),
                'formation_title' => $qcm->getCourse()?->getChapter()?->getModule()?->getFormation()?->getTitle(),
                'ip_address' => $this->container->get('request_stack')->getCurrentRequest()?->getClientIp(),
                'user_agent' => $this->container->get('request_stack')->getCurrentRequest()?->headers->get('User-Agent'),
                'timestamp' => new DateTime(),
            ]);

            // Get student's enrollment for the formation containing this QCM
            $this->logger->debug('Retrieving student enrollments for QCM access validation', [
                'student_id' => $student->getId(),
                'qcm_id' => $qcm->getId(),
            ]);

            $enrollments = $this->contentAccessService->getStudentEnrollments($student);

            $this->logger->debug('Student enrollments retrieved for QCM access', [
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
            $targetFormationId = $qcm->getCourse()?->getChapter()?->getModule()?->getFormation()?->getId();

            foreach ($enrollments as $e) {
                if ($e->getFormation() && $e->getFormation()->getId() === $targetFormationId) {
                    $enrollment = $e;
                    $this->logger->info('Valid enrollment found for QCM access', [
                        'enrollment_id' => $enrollment->getId(),
                        'formation_id' => $targetFormationId,
                        'student_id' => $student->getId(),
                        'qcm_id' => $qcm->getId(),
                        'enrollment_status' => $enrollment->getStatus(),
                    ]);
                    break;
                }
            }

            if (!$enrollment) {
                $this->logger->warning('No valid enrollment found for QCM access', [
                    'student_id' => $student->getId(),
                    'qcm_id' => $qcm->getId(),
                    'target_formation_id' => $targetFormationId,
                    'available_formation_ids' => array_map(static fn ($e) => $e->getFormation()?->getId(), $enrollments),
                ]);
            }

            // Get attempt information
            $this->logger->debug('Retrieving QCM attempt information', [
                'student_id' => $student->getId(),
                'qcm_id' => $qcm->getId(),
            ]);

            try {
                $attempts = $this->attemptService->getStudentAttempts($student, $qcm);
                $bestScore = $this->attemptService->getStudentBestScore($student, $qcm);
                $hasPassed = $this->attemptService->hasStudentPassed($student, $qcm);
                $canAttempt = $this->attemptService->canStudentAttempt($student, $qcm);
                $remainingAttempts = $this->attemptService->getRemainingAttempts($student, $qcm);
                $activeAttempt = $this->attemptService->getActiveAttempt($student, $qcm);

                $this->logger->info('QCM attempt information retrieved', [
                    'student_id' => $student->getId(),
                    'qcm_id' => $qcm->getId(),
                    'total_attempts' => count($attempts),
                    'best_score' => $bestScore,
                    'has_passed' => $hasPassed,
                    'can_attempt' => $canAttempt,
                    'remaining_attempts' => $remainingAttempts,
                    'has_active_attempt' => $activeAttempt !== null,
                    'active_attempt_id' => $activeAttempt?->getId(),
                ]);
            } catch (Exception $attemptException) {
                $this->logger->error('Failed to retrieve QCM attempt information', [
                    'student_id' => $student->getId(),
                    'qcm_id' => $qcm->getId(),
                    'error' => $attemptException->getMessage(),
                    'trace' => $attemptException->getTraceAsString(),
                ]);

                // Set default values to prevent template errors
                $attempts = [];
                $bestScore = null;
                $hasPassed = false;
                $canAttempt = false;
                $remainingAttempts = 0;
                $activeAttempt = null;
            }

            $this->logger->info('QCM view successful', [
                'qcm_id' => $qcm->getId(),
                'student_id' => $student->getId(),
                'enrollment_found' => $enrollment !== null,
                'total_attempts' => count($attempts ?? []),
                'can_attempt' => $canAttempt ?? false,
                'has_passed' => $hasPassed ?? false,
            ]);

            return $this->render('student/content/qcm/view.html.twig', [
                'qcm' => $qcm,
                'course' => $qcm->getCourse(),
                'chapter' => $qcm->getCourse()?->getChapter(),
                'module' => $qcm->getCourse()?->getChapter()?->getModule(),
                'formation' => $qcm->getCourse()?->getChapter()?->getModule()?->getFormation(),
                'enrollment' => $enrollment,
                'student' => $student,
                'attempts' => $attempts ?? [],
                'best_score' => $bestScore ?? null,
                'has_passed' => $hasPassed ?? false,
                'can_attempt' => $canAttempt ?? false,
                'remaining_attempts' => $remainingAttempts ?? 0,
                'active_attempt' => $activeAttempt,
                'page_title' => $qcm->getTitle(),
            ]);
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Invalid argument provided for QCM view', [
                'qcm_id' => $qcm->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Paramètres invalides pour l\'accès au QCM.');

            return $this->redirectToRoute('student_dashboard');
        } catch (LogicException $e) {
            $this->logger->error('Logic error in QCM view process', [
                'qcm_id' => $qcm->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur dans la logique d\'accès au QCM.');

            return $this->redirectToRoute('student_dashboard');
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error during QCM view', [
                'qcm_id' => $qcm->getId(),
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

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de l\'accès au QCM.');

            return $this->redirectToRoute('student_dashboard');
        }
    }

    /**
     * Take/interact with a QCM.
     */
    #[Route('/{id}/take', name: 'student_qcm_take', methods: ['GET', 'POST'])]
    #[IsGranted('interact', subject: 'qcm')]
    public function take(QCM $qcm, Request $request): Response
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student taking QCM', [
                'qcm_id' => $qcm->getId(),
                'qcm_title' => $qcm->getTitle(),
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'request_method' => $request->getMethod(),
                'is_submission' => $request->isMethod('POST'),
                'action' => $request->request->get('action', 'view'),
                'ip_address' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'timestamp' => new DateTime(),
            ]);

            // Check if student has an active attempt or can start a new one
            $this->logger->debug('Checking student access to QCM', [
                'student_id' => $student->getId(),
                'qcm_id' => $qcm->getId(),
            ]);

            try {
                $activeAttempt = $this->attemptService->getActiveAttempt($student, $qcm);
                $canAttempt = $this->attemptService->canStudentAttempt($student, $qcm);

                $this->logger->debug('Student QCM access checked', [
                    'student_id' => $student->getId(),
                    'qcm_id' => $qcm->getId(),
                    'has_active_attempt' => $activeAttempt !== null,
                    'can_start_new_attempt' => $canAttempt,
                ]);

                // Allow access if student has active attempt OR can start a new one
                if (!$activeAttempt && !$canAttempt) {
                    $this->logger->warning('Student cannot access QCM - no active attempt and cannot start new', [
                        'student_id' => $student->getId(),
                        'qcm_id' => $qcm->getId(),
                        'reason' => 'No active attempt and cannot start new attempt',
                    ]);

                    $this->addFlash('error', 'Vous ne pouvez plus commencer ce QCM.');

                    return $this->redirectToRoute('student_qcm_view', ['id' => $qcm->getId()]);
                }
            } catch (Exception $eligibilityException) {
                $this->logger->error('Failed to check QCM access', [
                    'student_id' => $student->getId(),
                    'qcm_id' => $qcm->getId(),
                    'error' => $eligibilityException->getMessage(),
                    'trace' => $eligibilityException->getTraceAsString(),
                ]);

                $this->addFlash('error', 'Erreur lors de la vérification des droits d\'accès au QCM.');

                return $this->redirectToRoute('student_qcm_view', ['id' => $qcm->getId()]);
            }

            // Get or create active attempt
            $this->logger->debug('Getting or creating active QCM attempt', [
                'student_id' => $student->getId(),
                'qcm_id' => $qcm->getId(),
                'has_existing_active' => $activeAttempt !== null,
            ]);

            try {
                if (!$activeAttempt) {
                    $attempt = $this->attemptService->getOrCreateActiveAttempt($student, $qcm);
                } else {
                    $attempt = $activeAttempt;
                }

                $this->logger->info('QCM attempt retrieved/created', [
                    'student_id' => $student->getId(),
                    'qcm_id' => $qcm->getId(),
                    'attempt_id' => $attempt->getId(),
                    'attempt_number' => $attempt->getAttemptNumber(),
                    'attempt_status' => $attempt->getStatus(),
                    'started_at' => $attempt->getStartedAt()?->format('Y-m-d H:i:s'),
                    'was_existing' => $activeAttempt !== null,
                ]);
            } catch (Exception $attemptException) {
                $this->logger->error('Failed to get or create QCM attempt', [
                    'student_id' => $student->getId(),
                    'qcm_id' => $qcm->getId(),
                    'error' => $attemptException->getMessage(),
                    'trace' => $attemptException->getTraceAsString(),
                ]);

                $this->addFlash('error', 'Erreur lors de la création de la tentative de QCM.');

                return $this->redirectToRoute('student_qcm_view', ['id' => $qcm->getId()]);
            }

            // Get randomized questions
            $this->logger->debug('Retrieving randomized questions for QCM', [
                'student_id' => $student->getId(),
                'qcm_id' => $qcm->getId(),
                'attempt_id' => $attempt->getId(),
            ]);

            try {
                $questions = $this->attemptService->getRandomizedQuestions($qcm);

                $this->logger->debug('Questions retrieved for QCM', [
                    'student_id' => $student->getId(),
                    'qcm_id' => $qcm->getId(),
                    'attempt_id' => $attempt->getId(),
                    'questions_count' => count($questions),
                ]);
            } catch (Exception $questionsException) {
                $this->logger->error('Failed to retrieve QCM questions', [
                    'student_id' => $student->getId(),
                    'qcm_id' => $qcm->getId(),
                    'attempt_id' => $attempt->getId(),
                    'error' => $questionsException->getMessage(),
                    'trace' => $questionsException->getTraceAsString(),
                ]);

                $this->addFlash('error', 'Erreur lors de la récupération des questions.');

                return $this->redirectToRoute('student_qcm_view', ['id' => $qcm->getId()]);
            }

            if ($request->isMethod('POST')) {
                $action = $request->request->get('action');

                $this->logger->debug('Processing QCM POST action', [
                    'student_id' => $student->getId(),
                    'qcm_id' => $qcm->getId(),
                    'attempt_id' => $attempt->getId(),
                    'action' => $action,
                ]);

                if ($action === 'submit') {
                    try {
                        // Save final answers
                        $questionData = $request->request->all();

                        $this->logger->info('Submitting QCM for final evaluation', [
                            'student_id' => $student->getId(),
                            'qcm_id' => $qcm->getId(),
                            'attempt_id' => $attempt->getId(),
                            'answered_questions' => count(array_filter($questionData, static fn ($key) => str_starts_with($key, 'question_'), ARRAY_FILTER_USE_KEY)),
                        ]);

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

                        $this->logger->info('QCM submitted successfully for evaluation', [
                            'student_id' => $student->getId(),
                            'qcm_id' => $qcm->getId(),
                            'attempt_id' => $attempt->getId(),
                            'final_score' => $attempt->getScore(),
                        ]);

                        $this->addFlash('success', 'QCM soumis avec succès!');

                        return $this->redirectToRoute('student_qcm_result', [
                            'id' => $qcm->getId(),
                            'attemptId' => $attempt->getId(),
                        ]);
                    } catch (Exception $submitException) {
                        $this->logger->error('Failed to submit QCM', [
                            'student_id' => $student->getId(),
                            'qcm_id' => $qcm->getId(),
                            'attempt_id' => $attempt->getId(),
                            'error' => $submitException->getMessage(),
                            'trace' => $submitException->getTraceAsString(),
                        ]);

                        $this->addFlash('error', 'Erreur lors de la soumission: ' . $submitException->getMessage());
                    }
                } elseif ($action === 'abandon') {
                    try {
                        $this->logger->info('Student abandoning QCM attempt', [
                            'student_id' => $student->getId(),
                            'qcm_id' => $qcm->getId(),
                            'attempt_id' => $attempt->getId(),
                        ]);

                        $this->attemptService->abandonAttempt($attempt);

                        $this->logger->info('QCM attempt abandoned successfully', [
                            'student_id' => $student->getId(),
                            'qcm_id' => $qcm->getId(),
                            'attempt_id' => $attempt->getId(),
                        ]);

                        $this->addFlash('info', 'QCM abandonné.');

                        return $this->redirectToRoute('student_qcm_view', ['id' => $qcm->getId()]);
                    } catch (Exception $abandonException) {
                        $this->logger->error('Failed to abandon QCM attempt', [
                            'student_id' => $student->getId(),
                            'qcm_id' => $qcm->getId(),
                            'attempt_id' => $attempt->getId(),
                            'error' => $abandonException->getMessage(),
                            'trace' => $abandonException->getTraceAsString(),
                        ]);

                        $this->addFlash('error', 'Erreur lors de l\'abandon: ' . $abandonException->getMessage());
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

            $this->logger->info('QCM take page loaded successfully', [
                'qcm_id' => $qcm->getId(),
                'student_id' => $student->getId(),
                'attempt_id' => $attempt->getId(),
                'questions_count' => count($questions),
                'answered_count' => $answeredCount,
                'progress_percentage' => $answeredCount > 0 ? round(($answeredCount / count($questions)) * 100, 2) : 0,
            ]);

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
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Invalid argument provided for QCM take', [
                'qcm_id' => $qcm->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Paramètres invalides pour le QCM.');

            return $this->redirectToRoute('student_qcm_view', ['id' => $qcm->getId()]);
        } catch (LogicException $e) {
            $this->logger->error('Logic error in QCM take process', [
                'qcm_id' => $qcm->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur dans la logique du QCM.');

            return $this->redirectToRoute('student_qcm_view', ['id' => $qcm->getId()]);
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error during QCM take', [
                'qcm_id' => $qcm->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_uri' => $request->getRequestUri(),
                'request_method' => $request->getMethod(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors du QCM.');

            return $this->redirectToRoute('student_qcm_view', ['id' => $qcm->getId()]);
        }
    }

    /**
     * Save answer via AJAX.
     */
    #[Route('/{id}/save-answer', name: 'student_qcm_save_answer', methods: ['POST'])]
    #[IsGranted('interact', subject: 'qcm')]
    public function saveAnswer(QCM $qcm, Request $request): JsonResponse
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student saving QCM answer via AJAX', [
                'qcm_id' => $qcm->getId(),
                'student_id' => $student->getId(),
                'content_length' => strlen($request->getContent()),
                'ip_address' => $request->getClientIp(),
                'timestamp' => new DateTime(),
            ]);

            try {
                $activeAttempt = $this->attemptService->getActiveAttempt($student, $qcm);

                $this->logger->debug('Active attempt retrieved for answer saving', [
                    'qcm_id' => $qcm->getId(),
                    'student_id' => $student->getId(),
                    'attempt_id' => $activeAttempt?->getId(),
                ]);
            } catch (Exception $attemptException) {
                $this->logger->error('Failed to get active attempt for answer saving', [
                    'qcm_id' => $qcm->getId(),
                    'student_id' => $student->getId(),
                    'error' => $attemptException->getMessage(),
                    'trace' => $attemptException->getTraceAsString(),
                ]);

                return new JsonResponse(['success' => false, 'message' => 'Erreur lors de la récupération de la tentative'], 500);
            }

            if (!$activeAttempt) {
                $this->logger->warning('No active attempt found for answer saving', [
                    'qcm_id' => $qcm->getId(),
                    'student_id' => $student->getId(),
                ]);

                return new JsonResponse(['success' => false, 'message' => 'Aucune tentative active'], 400);
            }

            // Get all form data and process answers for each question
            $questionData = $request->request->all();

            $this->logger->debug('Processing answer data for saving', [
                'qcm_id' => $qcm->getId(),
                'student_id' => $student->getId(),
                'attempt_id' => $activeAttempt->getId(),
                'data_keys' => array_keys($questionData),
                'answered_questions' => count(array_filter($questionData, static fn ($key) => str_starts_with($key, 'question_'), ARRAY_FILTER_USE_KEY)),
            ]);

            try {
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

                $this->logger->info('QCM answers saved successfully via AJAX', [
                    'qcm_id' => $qcm->getId(),
                    'student_id' => $student->getId(),
                    'attempt_id' => $activeAttempt->getId(),
                    'questions_answered' => count(array_filter($questionData, static fn ($key) => str_starts_with($key, 'question_'), ARRAY_FILTER_USE_KEY)),
                ]);

                return new JsonResponse(['success' => true, 'message' => 'Réponse sauvegardée']);
            } catch (Exception $saveException) {
                $this->logger->error('Failed to save QCM answers', [
                    'qcm_id' => $qcm->getId(),
                    'student_id' => $student->getId(),
                    'attempt_id' => $activeAttempt->getId(),
                    'error' => $saveException->getMessage(),
                    'trace' => $saveException->getTraceAsString(),
                ]);

                return new JsonResponse(['success' => false, 'message' => 'Erreur lors de la sauvegarde'], 500);
            }
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Invalid argument in QCM answer saving', [
                'qcm_id' => $qcm->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse(['success' => false, 'message' => 'Paramètres invalides'], 400);
        } catch (LogicException $e) {
            $this->logger->error('Logic error in QCM answer saving', [
                'qcm_id' => $qcm->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse(['success' => false, 'message' => 'Erreur de logique'], 500);
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error during QCM answer saving', [
                'qcm_id' => $qcm->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get remaining time for active attempt.
     */
    #[Route('/{id}/remaining-time', name: 'student_qcm_remaining_time', methods: ['GET'])]
    #[IsGranted('interact', subject: 'qcm')]
    public function getRemainingTime(QCM $qcm): JsonResponse
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->debug('Student requesting remaining time for QCM', [
                'qcm_id' => $qcm->getId(),
                'student_id' => $student->getId(),
                'timestamp' => new DateTime(),
            ]);

            try {
                $activeAttempt = $this->attemptService->getActiveAttempt($student, $qcm);

                $this->logger->debug('Active attempt retrieved for remaining time check', [
                    'qcm_id' => $qcm->getId(),
                    'student_id' => $student->getId(),
                    'attempt_id' => $activeAttempt?->getId(),
                    'has_active_attempt' => $activeAttempt !== null,
                ]);
            } catch (Exception $attemptException) {
                $this->logger->error('Failed to get active attempt for remaining time check', [
                    'qcm_id' => $qcm->getId(),
                    'student_id' => $student->getId(),
                    'error' => $attemptException->getMessage(),
                    'trace' => $attemptException->getTraceAsString(),
                ]);

                return new JsonResponse(['success' => false, 'message' => 'Erreur lors de la récupération de la tentative']);
            }

            if (!$activeAttempt) {
                $this->logger->warning('No active attempt found for remaining time check', [
                    'qcm_id' => $qcm->getId(),
                    'student_id' => $student->getId(),
                ]);

                return new JsonResponse(['success' => false, 'message' => 'Aucune tentative active']);
            }

            try {
                $remainingSeconds = $activeAttempt->getRemainingTimeSeconds();

                $this->logger->debug('Remaining time calculated for QCM attempt', [
                    'qcm_id' => $qcm->getId(),
                    'student_id' => $student->getId(),
                    'attempt_id' => $activeAttempt->getId(),
                    'remaining_seconds' => $remainingSeconds,
                    'expired' => $remainingSeconds === 0,
                ]);

                return new JsonResponse([
                    'success' => true,
                    'remaining_seconds' => $remainingSeconds,
                    'expired' => $remainingSeconds === 0,
                ]);
            } catch (Exception $timeException) {
                $this->logger->error('Failed to calculate remaining time for QCM attempt', [
                    'qcm_id' => $qcm->getId(),
                    'student_id' => $student->getId(),
                    'attempt_id' => $activeAttempt->getId(),
                    'error' => $timeException->getMessage(),
                    'trace' => $timeException->getTraceAsString(),
                ]);

                return new JsonResponse(['success' => false, 'message' => 'Erreur lors du calcul du temps restant'], 500);
            }
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error during remaining time check', [
                'qcm_id' => $qcm->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse(['success' => false, 'message' => 'Erreur inattendue'], 500);
        }
    }

    /**
     * View QCM results.
     */
    #[Route('/{id}/result/{attemptId}', name: 'student_qcm_result', methods: ['GET'])]
    #[IsGranted('view', subject: 'qcm')]
    public function result(QCM $qcm, int $attemptId): Response
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student viewing QCM result', [
                'qcm_id' => $qcm->getId(),
                'qcm_title' => $qcm->getTitle(),
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'attempt_id' => $attemptId,
                'ip_address' => $this->container->get('request_stack')->getCurrentRequest()?->getClientIp(),
                'user_agent' => $this->container->get('request_stack')->getCurrentRequest()?->headers->get('User-Agent'),
                'timestamp' => new DateTime(),
            ]);

            try {
                $attempts = $this->attemptService->getStudentAttempts($student, $qcm);

                $this->logger->debug('Retrieved student attempts for result view', [
                    'qcm_id' => $qcm->getId(),
                    'student_id' => $student->getId(),
                    'total_attempts' => count($attempts),
                    'target_attempt_id' => $attemptId,
                ]);
            } catch (Exception $attemptsException) {
                $this->logger->error('Failed to retrieve student attempts for result view', [
                    'qcm_id' => $qcm->getId(),
                    'student_id' => $student->getId(),
                    'attempt_id' => $attemptId,
                    'error' => $attemptsException->getMessage(),
                    'trace' => $attemptsException->getTraceAsString(),
                ]);

                $this->addFlash('error', 'Erreur lors de la récupération des tentatives.');

                return $this->redirectToRoute('student_qcm_view', ['id' => $qcm->getId()]);
            }

            $attempt = null;

            foreach ($attempts as $a) {
                if ($a->getId() === $attemptId) {
                    $attempt = $a;
                    break;
                }
            }

            if (!$attempt) {
                $this->logger->warning('Attempt not found for result view', [
                    'qcm_id' => $qcm->getId(),
                    'student_id' => $student->getId(),
                    'attempt_id' => $attemptId,
                    'available_attempt_ids' => array_map(static fn ($a) => $a->getId(), $attempts),
                ]);

                $this->addFlash('error', 'Tentative introuvable.');

                throw $this->createNotFoundException('Attempt not found');
            }

            // Show results only if QCM is configured to show them
            $showCorrectAnswers = $qcm->isShowCorrectAnswers();
            $showExplanations = $qcm->isShowExplanations();

            // Get additional data needed for the template
            try {
                $questions = $this->attemptService->getRandomizedQuestions($qcm);
                $canRetry = $this->attemptService->canStudentAttempt($student, $qcm);

                // Get other attempts for the sidebar
                $otherAttempts = array_filter($attempts, static fn ($a) => $a->getId() !== $attemptId);

                // Calculate statistics
                $correctAnswers = 0;
                $incorrectAnswers = 0;
                $unansweredCount = 0;
                $correctQuestionIds = [];
                $answeredQuestionIds = [];
                $studentAnswers = [];

                foreach ($questions as $index => $question) {
                    $userAnswer = $attempt->getAnswerForQuestion($index);
                    $studentAnswers[$index] = $userAnswer ?? [];

                    if (empty($userAnswer)) {
                        $unansweredCount++;
                    } else {
                        $answeredQuestionIds[] = $index;

                        // Check if answer is correct
                        $correctOptions = $question['correct_answers'] ?? [];

                        // Compare user answer with correct options
                        sort($correctOptions);
                        sort($userAnswer);

                        if ($correctOptions === $userAnswer) {
                            $correctAnswers++;
                            $correctQuestionIds[] = $index;
                        } else {
                            $incorrectAnswers++;
                        }
                    }
                }

                $this->logger->debug('Result template data calculated', [
                    'qcm_id' => $qcm->getId(),
                    'student_id' => $student->getId(),
                    'attempt_id' => $attemptId,
                    'questions_count' => count($questions),
                    'correct_answers' => $correctAnswers,
                    'incorrect_answers' => $incorrectAnswers,
                    'unanswered_count' => $unansweredCount,
                    'can_retry' => $canRetry,
                    'other_attempts_count' => count($otherAttempts),
                ]);
            } catch (Exception $dataException) {
                $this->logger->error('Failed to calculate result template data', [
                    'qcm_id' => $qcm->getId(),
                    'student_id' => $student->getId(),
                    'attempt_id' => $attemptId,
                    'error' => $dataException->getMessage(),
                    'trace' => $dataException->getTraceAsString(),
                ]);

                // Set default values to prevent template errors
                $questions = [];
                $canRetry = false;
                $otherAttempts = [];
                $correctAnswers = 0;
                $incorrectAnswers = 0;
                $unansweredCount = 0;
                $correctQuestionIds = [];
                $answeredQuestionIds = [];
                $studentAnswers = [];
            }

            $this->logger->info('QCM result view successful', [
                'qcm_id' => $qcm->getId(),
                'student_id' => $student->getId(),
                'attempt_id' => $attemptId,
                'attempt_score' => $attempt->getScore(),
                'attempt_status' => $attempt->getStatus(),
                'attempt_number' => $attempt->getAttemptNumber(),
                'show_correct_answers' => $showCorrectAnswers,
                'show_explanations' => $showExplanations,
                'passed' => $attempt->getScore() >= $qcm->getPassingScore(),
            ]);

            return $this->render('student/content/qcm/result.html.twig', [
                'qcm' => $qcm,
                'course' => $qcm->getCourse(),
                'chapter' => $qcm->getCourse()?->getChapter(),
                'module' => $qcm->getCourse()?->getChapter()?->getModule(),
                'formation' => $qcm->getCourse()?->getChapter()?->getModule()?->getFormation(),
                'attempt' => $attempt,
                'student' => $student,
                'questions' => $questions,
                'show_correct_answers' => $showCorrectAnswers,
                'show_explanations' => $showExplanations,
                'can_retry' => $canRetry,
                'other_attempts' => $otherAttempts,
                'correct_answers' => $correctAnswers,
                'incorrect_answers' => $incorrectAnswers,
                'unanswered_count' => $unansweredCount,
                'correct_question_ids' => $correctQuestionIds,
                'answered_question_ids' => $answeredQuestionIds,
                'student_answers' => $studentAnswers,
                'page_title' => 'Résultat: ' . $qcm->getTitle(),
            ]);
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Invalid argument provided for QCM result view', [
                'qcm_id' => $qcm->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'attempt_id' => $attemptId,
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Paramètres invalides pour l\'affichage du résultat.');

            return $this->redirectToRoute('student_qcm_view', ['id' => $qcm->getId()]);
        } catch (LogicException $e) {
            $this->logger->error('Logic error in QCM result view', [
                'qcm_id' => $qcm->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'attempt_id' => $attemptId,
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur de logique lors de l\'affichage du résultat.');

            return $this->redirectToRoute('student_qcm_view', ['id' => $qcm->getId()]);
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error during QCM result view', [
                'qcm_id' => $qcm->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'attempt_id' => $attemptId,
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_uri' => $this->container->get('request_stack')->getCurrentRequest()?->getRequestUri(),
                'request_method' => $this->container->get('request_stack')->getCurrentRequest()?->getMethod(),
            ]);

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de l\'affichage du résultat.');

            return $this->redirectToRoute('student_qcm_view', ['id' => $qcm->getId()]);
        }
    }

    /**
     * Retry a QCM (start new attempt).
     */
    #[Route('/{id}/retry', name: 'student_qcm_retry', methods: ['POST'])]
    #[IsGranted('interact', subject: 'qcm')]
    public function retry(QCM $qcm): Response
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student attempting to retry QCM', [
                'qcm_id' => $qcm->getId(),
                'qcm_title' => $qcm->getTitle(),
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'ip_address' => $this->container->get('request_stack')->getCurrentRequest()?->getClientIp(),
                'user_agent' => $this->container->get('request_stack')->getCurrentRequest()?->headers->get('User-Agent'),
                'timestamp' => new DateTime(),
            ]);

            try {
                $canAttempt = $this->attemptService->canStudentAttempt($student, $qcm);

                $this->logger->debug('Checked student eligibility for QCM retry', [
                    'qcm_id' => $qcm->getId(),
                    'student_id' => $student->getId(),
                    'can_attempt' => $canAttempt,
                ]);
            } catch (Exception $eligibilityException) {
                $this->logger->error('Failed to check QCM retry eligibility', [
                    'qcm_id' => $qcm->getId(),
                    'student_id' => $student->getId(),
                    'error' => $eligibilityException->getMessage(),
                    'trace' => $eligibilityException->getTraceAsString(),
                ]);

                $this->addFlash('error', 'Erreur lors de la vérification des droits de nouvelle tentative.');

                return $this->redirectToRoute('student_qcm_view', ['id' => $qcm->getId()]);
            }

            if (!$canAttempt) {
                $this->logger->warning('Student cannot retry QCM - maximum attempts reached', [
                    'qcm_id' => $qcm->getId(),
                    'student_id' => $student->getId(),
                    'max_attempts' => $qcm->getMaxAttempts(),
                ]);

                $this->addFlash('error', 'Vous avez atteint le nombre maximum de tentatives pour ce QCM.');

                return $this->redirectToRoute('student_qcm_view', ['id' => $qcm->getId()]);
            }

            $this->logger->info('QCM retry request successful - redirecting to take page', [
                'qcm_id' => $qcm->getId(),
                'student_id' => $student->getId(),
            ]);

            return $this->redirectToRoute('student_qcm_take', ['id' => $qcm->getId()]);
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Invalid argument provided for QCM retry', [
                'qcm_id' => $qcm->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Paramètres invalides pour la nouvelle tentative.');

            return $this->redirectToRoute('student_qcm_view', ['id' => $qcm->getId()]);
        } catch (LogicException $e) {
            $this->logger->error('Logic error in QCM retry process', [
                'qcm_id' => $qcm->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur de logique lors de la nouvelle tentative.');

            return $this->redirectToRoute('student_qcm_view', ['id' => $qcm->getId()]);
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error during QCM retry', [
                'qcm_id' => $qcm->getId(),
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

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de la nouvelle tentative.');

            return $this->redirectToRoute('student_qcm_view', ['id' => $qcm->getId()]);
        }
    }
}
