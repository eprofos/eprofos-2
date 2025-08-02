<?php

declare(strict_types=1);

namespace App\Controller\Student\Content;

use App\Entity\Training\Exercise;
use App\Entity\User\Student;
use App\Repository\Training\ExerciseRepository;
use App\Service\Security\ContentAccessService;
use App\Service\Student\ExerciseSubmissionService;
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
        private readonly ExerciseSubmissionService $submissionService,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * View a specific exercise with access control.
     */
    #[Route('/{id}', name: 'student_exercise_view', methods: ['GET'])]
    #[IsGranted('view', subject: 'exercise')]
    public function view(Exercise $exercise): Response
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student attempting to view exercise', [
                'exercise_id' => $exercise->getId(),
                'exercise_title' => $exercise->getTitle(),
                'exercise_type' => $exercise->getType(),
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'course_id' => $exercise->getCourse()?->getId(),
                'chapter_id' => $exercise->getCourse()?->getChapter()?->getId(),
                'module_id' => $exercise->getCourse()?->getChapter()?->getModule()?->getId(),
                'formation_id' => $exercise->getCourse()?->getChapter()?->getModule()?->getFormation()?->getId(),
                'formation_title' => $exercise->getCourse()?->getChapter()?->getModule()?->getFormation()?->getTitle(),
                'exercise_duration_minutes' => $exercise->getEstimatedDurationMinutes(),
                'exercise_max_attempts' => $exercise->getMaxAttempts(),
                'ip_address' => $this->container->get('request_stack')->getCurrentRequest()?->getClientIp(),
                'user_agent' => $this->container->get('request_stack')->getCurrentRequest()?->headers->get('User-Agent'),
                'timestamp' => new DateTime(),
            ]);

            // Get student's enrollment for the formation containing this exercise
            $this->logger->debug('Retrieving student enrollments for exercise access validation', [
                'student_id' => $student->getId(),
                'exercise_id' => $exercise->getId(),
            ]);

            $enrollments = $this->contentAccessService->getStudentEnrollments($student);

            $this->logger->debug('Student enrollments retrieved for exercise access', [
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
            $targetFormationId = $exercise->getCourse()?->getChapter()?->getModule()?->getFormation()?->getId();

            foreach ($enrollments as $e) {
                if ($e->getFormation() && $e->getFormation()->getId() === $targetFormationId) {
                    $enrollment = $e;
                    $this->logger->info('Valid enrollment found for exercise access', [
                        'enrollment_id' => $enrollment->getId(),
                        'formation_id' => $targetFormationId,
                        'student_id' => $student->getId(),
                        'exercise_id' => $exercise->getId(),
                        'enrollment_status' => $enrollment->getStatus(),
                    ]);
                    break;
                }
            }

            if (!$enrollment) {
                $this->logger->warning('No valid enrollment found for exercise access', [
                    'student_id' => $student->getId(),
                    'exercise_id' => $exercise->getId(),
                    'target_formation_id' => $targetFormationId,
                    'available_formation_ids' => array_map(static fn ($e) => $e->getFormation()?->getId(), $enrollments),
                ]);
            }

            // Get submission information
            $this->logger->debug('Retrieving exercise submission information', [
                'student_id' => $student->getId(),
                'exercise_id' => $exercise->getId(),
            ]);

            try {
                $submissions = $this->submissionService->getStudentSubmissions($student, $exercise);
                $bestScore = $this->submissionService->getStudentBestScore($student, $exercise);
                $hasPassed = $this->submissionService->hasStudentPassed($student, $exercise);
                $canAttempt = $this->submissionService->canStudentAttempt($student, $exercise);

                $this->logger->info('Exercise submission information retrieved', [
                    'student_id' => $student->getId(),
                    'exercise_id' => $exercise->getId(),
                    'total_submissions' => count($submissions),
                    'best_score' => $bestScore,
                    'has_passed' => $hasPassed,
                    'can_attempt' => $canAttempt,
                    'remaining_attempts' => $exercise->getMaxAttempts() ? ($exercise->getMaxAttempts() - count($submissions)) : 'unlimited',
                ]);
            } catch (Exception $submissionException) {
                $this->logger->error('Failed to retrieve exercise submission information', [
                    'student_id' => $student->getId(),
                    'exercise_id' => $exercise->getId(),
                    'error' => $submissionException->getMessage(),
                    'trace' => $submissionException->getTraceAsString(),
                ]);

                // Set default values to prevent template errors
                $submissions = [];
                $bestScore = null;
                $hasPassed = false;
                $canAttempt = false;
            }

            $this->logger->info('Exercise view successful', [
                'exercise_id' => $exercise->getId(),
                'student_id' => $student->getId(),
                'enrollment_found' => $enrollment !== null,
                'exercise_type' => $exercise->getType(),
                'can_attempt' => $canAttempt ?? false,
                'has_passed' => $hasPassed ?? false,
            ]);

            return $this->render('student/content/exercise/view.html.twig', [
                'exercise' => $exercise,
                'course' => $exercise->getCourse(),
                'chapter' => $exercise->getCourse()?->getChapter(),
                'module' => $exercise->getCourse()?->getChapter()?->getModule(),
                'formation' => $exercise->getCourse()?->getChapter()?->getModule()?->getFormation(),
                'enrollment' => $enrollment,
                'student' => $student,
                'submissions' => $submissions ?? [],
                'best_score' => $bestScore ?? null,
                'has_passed' => $hasPassed ?? false,
                'can_attempt' => $canAttempt ?? false,
                'page_title' => $exercise->getTitle(),
            ]);
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Invalid argument provided for exercise view', [
                'exercise_id' => $exercise->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Paramètres invalides pour l\'accès à l\'exercice.');

            return $this->redirectToRoute('student_dashboard');
        } catch (LogicException $e) {
            $this->logger->error('Logic error in exercise view process', [
                'exercise_id' => $exercise->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur dans la logique d\'accès à l\'exercice.');

            return $this->redirectToRoute('student_dashboard');
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error during exercise view', [
                'exercise_id' => $exercise->getId(),
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

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de l\'accès à l\'exercice.');

            return $this->redirectToRoute('student_dashboard');
        }
    }

    /**
     * Start/interact with an exercise.
     */
    #[Route('/{id}/start', name: 'student_exercise_start', methods: ['GET', 'POST'])]
    #[IsGranted('interact', subject: 'exercise')]
    public function start(Exercise $exercise, Request $request): Response
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student attempting to start exercise', [
                'exercise_id' => $exercise->getId(),
                'exercise_title' => $exercise->getTitle(),
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'request_method' => $request->getMethod(),
                'is_submission' => $request->isMethod('POST'),
                'action' => $request->request->get('action', 'progress'),
                'ip_address' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'timestamp' => new DateTime(),
            ]);

            // Check if student can attempt this exercise
            $this->logger->debug('Checking student eligibility for exercise attempt', [
                'student_id' => $student->getId(),
                'exercise_id' => $exercise->getId(),
            ]);

            try {
                $canAttempt = $this->submissionService->canStudentAttempt($student, $exercise);

                $this->logger->debug('Student exercise attempt eligibility checked', [
                    'student_id' => $student->getId(),
                    'exercise_id' => $exercise->getId(),
                    'can_attempt' => $canAttempt,
                ]);
            } catch (Exception $eligibilityException) {
                $this->logger->error('Failed to check exercise attempt eligibility', [
                    'student_id' => $student->getId(),
                    'exercise_id' => $exercise->getId(),
                    'error' => $eligibilityException->getMessage(),
                    'trace' => $eligibilityException->getTraceAsString(),
                ]);

                $this->addFlash('error', 'Erreur lors de la vérification des droits d\'accès à l\'exercice.');

                return $this->redirectToRoute('student_exercise_view', ['id' => $exercise->getId()]);
            }

            if (!$canAttempt) {
                $this->logger->warning('Student cannot attempt exercise', [
                    'student_id' => $student->getId(),
                    'exercise_id' => $exercise->getId(),
                    'reason' => 'Eligibility check failed',
                ]);

                $this->addFlash('error', 'Vous ne pouvez plus démarrer cette exercice.');

                return $this->redirectToRoute('student_exercise_view', ['id' => $exercise->getId()]);
            }

            // Get or create submission
            $this->logger->debug('Getting or creating exercise submission', [
                'student_id' => $student->getId(),
                'exercise_id' => $exercise->getId(),
            ]);

            try {
                $submission = $this->submissionService->getOrCreateSubmission($student, $exercise);

                $this->logger->info('Exercise submission retrieved/created', [
                    'student_id' => $student->getId(),
                    'exercise_id' => $exercise->getId(),
                    'submission_id' => $submission->getId(),
                    'submission_status' => $submission->getStatus(),
                    'attempt_number' => $submission->getAttemptNumber(),
                ]);
            } catch (Exception $submissionException) {
                $this->logger->error('Failed to get or create exercise submission', [
                    'student_id' => $student->getId(),
                    'exercise_id' => $exercise->getId(),
                    'error' => $submissionException->getMessage(),
                    'trace' => $submissionException->getTraceAsString(),
                ]);

                $this->addFlash('error', 'Erreur lors de la création de la soumission d\'exercice.');

                return $this->redirectToRoute('student_exercise_view', ['id' => $exercise->getId()]);
            }

            if ($request->isMethod('POST')) {
                $submissionData = $request->request->all();
                $action = $request->request->get('action', 'save');

                $this->logger->debug('Processing exercise submission data', [
                    'student_id' => $student->getId(),
                    'exercise_id' => $exercise->getId(),
                    'submission_id' => $submission->getId(),
                    'action' => $action,
                    'data_keys' => array_keys($submissionData),
                    'data_count' => count($submissionData),
                ]);

                try {
                    $this->submissionService->saveSubmissionData($submission, $submissionData);

                    $this->logger->info('Exercise submission data saved', [
                        'student_id' => $student->getId(),
                        'exercise_id' => $exercise->getId(),
                        'submission_id' => $submission->getId(),
                        'action' => $action,
                    ]);

                    if ($action === 'submit') {
                        $this->logger->info('Submitting exercise for final evaluation', [
                            'student_id' => $student->getId(),
                            'exercise_id' => $exercise->getId(),
                            'submission_id' => $submission->getId(),
                        ]);

                        $this->submissionService->submitExercise($submission);

                        $this->logger->info('Exercise submitted successfully for evaluation', [
                            'student_id' => $student->getId(),
                            'exercise_id' => $exercise->getId(),
                            'submission_id' => $submission->getId(),
                        ]);

                        $this->addFlash('success', 'Exercice soumis avec succès!');

                        return $this->redirectToRoute('student_exercise_view', ['id' => $exercise->getId()]);
                    }

                    $this->addFlash('success', 'Progression sauvegardée.');
                } catch (Exception $saveException) {
                    $this->logger->error('Failed to save exercise submission data', [
                        'student_id' => $student->getId(),
                        'exercise_id' => $exercise->getId(),
                        'submission_id' => $submission->getId(),
                        'action' => $action,
                        'error' => $saveException->getMessage(),
                        'trace' => $saveException->getTraceAsString(),
                    ]);

                    $this->addFlash('error', 'Erreur lors de la sauvegarde: ' . $saveException->getMessage());
                }
            }

            $this->logger->info('Exercise start page loaded successfully', [
                'exercise_id' => $exercise->getId(),
                'student_id' => $student->getId(),
                'submission_id' => $submission->getId(),
            ]);

            return $this->render('student/content/exercise/start.html.twig', [
                'exercise' => $exercise,
                'submission' => $submission,
                'student' => $student,
                'page_title' => 'Exercice: ' . $exercise->getTitle(),
            ]);
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Invalid argument provided for exercise start', [
                'exercise_id' => $exercise->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Paramètres invalides pour le démarrage de l\'exercice.');

            return $this->redirectToRoute('student_exercise_view', ['id' => $exercise->getId()]);
        } catch (LogicException $e) {
            $this->logger->error('Logic error in exercise start process', [
                'exercise_id' => $exercise->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur dans la logique de démarrage de l\'exercice.');

            return $this->redirectToRoute('student_exercise_view', ['id' => $exercise->getId()]);
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error during exercise start', [
                'exercise_id' => $exercise->getId(),
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

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors du démarrage de l\'exercice.');

            return $this->redirectToRoute('student_exercise_view', ['id' => $exercise->getId()]);
        }
    }

    /**
     * Auto-save exercise data via AJAX.
     */
    #[Route('/{id}/autosave', name: 'student_exercise_autosave', methods: ['POST'])]
    #[IsGranted('interact', subject: 'exercise')]
    public function autosave(Exercise $exercise, Request $request): JsonResponse
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student autosave request for exercise', [
                'exercise_id' => $exercise->getId(),
                'student_id' => $student->getId(),
                'content_length' => strlen($request->getContent()),
                'ip_address' => $request->getClientIp(),
                'timestamp' => new DateTime(),
            ]);

            try {
                $submission = $this->submissionService->getOrCreateSubmission($student, $exercise);

                $this->logger->debug('Exercise submission retrieved for autosave', [
                    'exercise_id' => $exercise->getId(),
                    'student_id' => $student->getId(),
                    'submission_id' => $submission->getId(),
                ]);
            } catch (Exception $submissionException) {
                $this->logger->error('Failed to get or create submission for autosave', [
                    'exercise_id' => $exercise->getId(),
                    'student_id' => $student->getId(),
                    'error' => $submissionException->getMessage(),
                    'trace' => $submissionException->getTraceAsString(),
                ]);

                return new JsonResponse([
                    'success' => false,
                    'message' => 'Erreur lors de la récupération de la soumission',
                ], 500);
            }

            $data = json_decode($request->getContent(), true);

            if ($data === null) {
                $this->logger->warning('Invalid JSON data received for autosave', [
                    'exercise_id' => $exercise->getId(),
                    'student_id' => $student->getId(),
                    'raw_content' => $request->getContent(),
                ]);

                return new JsonResponse([
                    'success' => false,
                    'message' => 'Données JSON invalides',
                ], 400);
            }

            $this->logger->debug('Processing autosave data', [
                'exercise_id' => $exercise->getId(),
                'student_id' => $student->getId(),
                'submission_id' => $submission->getId(),
                'data_keys' => array_keys($data),
                'data_count' => count($data),
            ]);

            $this->submissionService->saveSubmissionData($submission, $data);

            $this->logger->info('Autosave completed successfully', [
                'exercise_id' => $exercise->getId(),
                'student_id' => $student->getId(),
                'submission_id' => $submission->getId(),
            ]);

            return new JsonResponse(['success' => true, 'message' => 'Sauvegardé automatiquement']);
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Invalid argument in exercise autosave', [
                'exercise_id' => $exercise->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse(['success' => false, 'message' => 'Paramètres invalides'], 400);
        } catch (LogicException $e) {
            $this->logger->error('Logic error in exercise autosave', [
                'exercise_id' => $exercise->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse(['success' => false, 'message' => 'Erreur de logique'], 500);
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error during exercise autosave', [
                'exercise_id' => $exercise->getId(),
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
     * Submit exercise for grading.
     */
    #[Route('/{id}/submit', name: 'student_exercise_submit', methods: ['POST'])]
    #[IsGranted('interact', subject: 'exercise')]
    public function submit(Exercise $exercise, Request $request): Response
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student submitting exercise for final grading', [
                'exercise_id' => $exercise->getId(),
                'exercise_title' => $exercise->getTitle(),
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'ip_address' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
                'timestamp' => new DateTime(),
            ]);

            try {
                $submission = $this->submissionService->getOrCreateSubmission($student, $exercise);

                $this->logger->debug('Exercise submission retrieved for final submission', [
                    'exercise_id' => $exercise->getId(),
                    'student_id' => $student->getId(),
                    'submission_id' => $submission->getId(),
                    'current_status' => $submission->getStatus(),
                ]);
            } catch (Exception $submissionException) {
                $this->logger->error('Failed to get or create submission for final submission', [
                    'exercise_id' => $exercise->getId(),
                    'student_id' => $student->getId(),
                    'error' => $submissionException->getMessage(),
                    'trace' => $submissionException->getTraceAsString(),
                ]);

                $this->addFlash('error', 'Erreur lors de la récupération de la soumission.');

                return $this->redirectToRoute('student_exercise_view', ['id' => $exercise->getId()]);
            }

            // Save final data
            $submissionData = $request->request->all();

            $this->logger->debug('Saving final submission data', [
                'exercise_id' => $exercise->getId(),
                'student_id' => $student->getId(),
                'submission_id' => $submission->getId(),
                'data_keys' => array_keys($submissionData),
                'data_count' => count($submissionData),
            ]);

            $this->submissionService->saveSubmissionData($submission, $submissionData);

            // Submit for grading
            $this->logger->info('Submitting exercise for final grading', [
                'exercise_id' => $exercise->getId(),
                'student_id' => $student->getId(),
                'submission_id' => $submission->getId(),
            ]);

            $this->submissionService->submitExercise($submission);

            $this->logger->info('Exercise submitted successfully for final grading', [
                'exercise_id' => $exercise->getId(),
                'student_id' => $student->getId(),
                'submission_id' => $submission->getId(),
                'final_status' => $submission->getStatus(),
            ]);

            $this->addFlash('success', 'Exercice soumis avec succès pour évaluation!');
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Invalid argument in exercise final submission', [
                'exercise_id' => $exercise->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Paramètres invalides pour la soumission de l\'exercice.');
        } catch (LogicException $e) {
            $this->logger->error('Logic error in exercise final submission', [
                'exercise_id' => $exercise->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur de logique lors de la soumission de l\'exercice.');
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error during exercise final submission', [
                'exercise_id' => $exercise->getId(),
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
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student viewing exercise result', [
                'exercise_id' => $exercise->getId(),
                'exercise_title' => $exercise->getTitle(),
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'submission_id' => $submissionId,
                'ip_address' => $this->container->get('request_stack')->getCurrentRequest()?->getClientIp(),
                'user_agent' => $this->container->get('request_stack')->getCurrentRequest()?->headers->get('User-Agent'),
                'timestamp' => new DateTime(),
            ]);

            try {
                $submissions = $this->submissionService->getStudentSubmissions($student, $exercise);

                $this->logger->debug('Retrieved student submissions for result view', [
                    'exercise_id' => $exercise->getId(),
                    'student_id' => $student->getId(),
                    'total_submissions' => count($submissions),
                    'target_submission_id' => $submissionId,
                ]);
            } catch (Exception $submissionException) {
                $this->logger->error('Failed to retrieve student submissions for result view', [
                    'exercise_id' => $exercise->getId(),
                    'student_id' => $student->getId(),
                    'submission_id' => $submissionId,
                    'error' => $submissionException->getMessage(),
                    'trace' => $submissionException->getTraceAsString(),
                ]);

                $this->addFlash('error', 'Erreur lors de la récupération des soumissions.');

                return $this->redirectToRoute('student_exercise_view', ['id' => $exercise->getId()]);
            }

            $submission = null;

            foreach ($submissions as $s) {
                if ($s->getId() === $submissionId) {
                    $submission = $s;
                    break;
                }
            }

            if (!$submission) {
                $this->logger->warning('Submission not found for result view', [
                    'exercise_id' => $exercise->getId(),
                    'student_id' => $student->getId(),
                    'submission_id' => $submissionId,
                    'available_submission_ids' => array_map(static fn ($s) => $s->getId(), $submissions),
                ]);

                $this->addFlash('error', 'Soumission introuvable.');

                throw $this->createNotFoundException('Submission not found');
            }

            $this->logger->info('Exercise result view successful', [
                'exercise_id' => $exercise->getId(),
                'student_id' => $student->getId(),
                'submission_id' => $submissionId,
                'submission_status' => $submission->getStatus(),
                'submission_score' => $submission->getScore(),
                'submission_attempt' => $submission->getAttemptNumber(),
            ]);

            return $this->render('student/content/exercise/result.html.twig', [
                'exercise' => $exercise,
                'submission' => $submission,
                'student' => $student,
                'page_title' => 'Résultat: ' . $exercise->getTitle(),
            ]);
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Invalid argument provided for exercise result view', [
                'exercise_id' => $exercise->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'submission_id' => $submissionId,
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Paramètres invalides pour l\'affichage du résultat.');

            return $this->redirectToRoute('student_exercise_view', ['id' => $exercise->getId()]);
        } catch (LogicException $e) {
            $this->logger->error('Logic error in exercise result view', [
                'exercise_id' => $exercise->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'submission_id' => $submissionId,
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur de logique lors de l\'affichage du résultat.');

            return $this->redirectToRoute('student_exercise_view', ['id' => $exercise->getId()]);
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error during exercise result view', [
                'exercise_id' => $exercise->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'submission_id' => $submissionId,
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

            return $this->redirectToRoute('student_exercise_view', ['id' => $exercise->getId()]);
        }
    }
}
