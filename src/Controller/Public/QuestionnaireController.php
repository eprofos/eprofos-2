<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Entity\Assessment\Question;
use App\Entity\Assessment\Questionnaire;
use App\Entity\Assessment\QuestionnaireResponse;
use App\Entity\Assessment\QuestionResponse;
use App\Repository\Assessment\QuestionnaireRepository;
use App\Repository\Assessment\QuestionnaireResponseRepository;
use App\Repository\Training\FormationRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Public controller for questionnaire completion.
 */
#[Route('/questionnaire')]
class QuestionnaireController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private QuestionnaireRepository $questionnaireRepository,
        private QuestionnaireResponseRepository $responseRepository,
        private FormationRepository $formationRepository,
        private LoggerInterface $logger,
    ) {}

    #[Route('/complete/{token}', name: 'questionnaire_complete', methods: ['GET'])]
    public function complete(string $token): Response
    {
        $this->logger->info('Starting questionnaire completion', [
            'token' => $token,
            'method' => 'complete',
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ]);

        try {
            $response = $this->responseRepository->findByToken($token);

            if (!$response) {
                $this->logger->warning('Invalid questionnaire token provided', [
                    'token' => $token,
                    'action' => 'token_validation_failed',
                ]);

                throw $this->createNotFoundException('Token de questionnaire invalide.');
            }

            $this->logger->info('Questionnaire response found', [
                'token' => $token,
                'response_id' => $response->getId(),
                'questionnaire_id' => $response->getQuestionnaire()->getId(),
                'questionnaire_title' => $response->getQuestionnaire()->getTitle(),
                'current_status' => $response->getStatus(),
                'is_completed' => $response->isCompleted(),
            ]);

            if ($response->isCompleted()) {
                $this->logger->info('Questionnaire already completed, showing completion page', [
                    'token' => $token,
                    'response_id' => $response->getId(),
                    'completed_at' => $response->getCompletedAt()?->format('Y-m-d H:i:s'),
                ]);

                return $this->render('public/questionnaire/already_completed.html.twig', [
                    'response' => $response,
                ]);
            }

            $questionnaire = $response->getQuestionnaire();

            if (!$questionnaire->isActive()) {
                $this->logger->warning('Attempting to access inactive questionnaire', [
                    'token' => $token,
                    'questionnaire_id' => $questionnaire->getId(),
                    'questionnaire_title' => $questionnaire->getTitle(),
                    'is_active' => $questionnaire->isActive(),
                ]);

                throw $this->createNotFoundException('Ce questionnaire n\'est plus actif.');
            }

            // Mark as started if not already
            if ($response->getStatus() === QuestionnaireResponse::STATUS_STARTED && !$response->getStartedAt()) {
                $this->logger->info('Marking questionnaire as started', [
                    'token' => $token,
                    'response_id' => $response->getId(),
                    'previous_status' => $response->getStatus(),
                ]);

                $response->markAsStarted();
                $this->entityManager->flush();

                $this->logger->info('Questionnaire marked as started successfully', [
                    'token' => $token,
                    'response_id' => $response->getId(),
                    'started_at' => $response->getStartedAt()?->format('Y-m-d H:i:s'),
                ]);
            }

            $currentStep = $response->getCurrentStep() ?? 1;
            $totalSteps = $questionnaire->getStepCount();

            $this->logger->info('Redirecting to questionnaire step', [
                'token' => $token,
                'response_id' => $response->getId(),
                'current_step' => $currentStep,
                'total_steps' => $totalSteps,
            ]);

            // Redirect to the current step
            return $this->redirectToRoute('questionnaire_step', [
                'token' => $token,
                'step' => $currentStep,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error in questionnaire completion', [
                'token' => $token,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            if ($e instanceof NotFoundHttpException) {
                throw $e;
            }

            $this->addFlash('error', 'Une erreur est survenue lors du chargement du questionnaire.');

            throw $e;
        }
    }

    #[Route('/step/{token}/{step}', name: 'questionnaire_step', methods: ['GET', 'POST'], requirements: ['step' => '\d+'])]
    public function step(Request $request, string $token, int $step): Response
    {
        $this->logger->info('Processing questionnaire step', [
            'token' => $token,
            'step' => $step,
            'method' => $request->getMethod(),
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'request_time' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);

        try {
            $response = $this->responseRepository->findByToken($token);

            if (!$response) {
                $this->logger->warning('Invalid token in step processing', [
                    'token' => $token,
                    'step' => $step,
                    'action' => 'token_validation_failed',
                ]);

                throw $this->createNotFoundException('Token de questionnaire invalide.');
            }

            $this->logger->info('Questionnaire response found for step', [
                'token' => $token,
                'step' => $step,
                'response_id' => $response->getId(),
                'questionnaire_id' => $response->getQuestionnaire()->getId(),
                'current_step' => $response->getCurrentStep(),
                'status' => $response->getStatus(),
            ]);

            if ($response->isCompleted()) {
                $this->logger->info('Questionnaire already completed, redirecting', [
                    'token' => $token,
                    'step' => $step,
                    'response_id' => $response->getId(),
                    'completed_at' => $response->getCompletedAt()?->format('Y-m-d H:i:s'),
                ]);

                return $this->redirectToRoute('questionnaire_complete', ['token' => $token]);
            }

            $questionnaire = $response->getQuestionnaire();
            $totalSteps = $questionnaire->getStepCount();

            $this->logger->info('Questionnaire step validation', [
                'token' => $token,
                'requested_step' => $step,
                'total_steps' => $totalSteps,
                'current_step' => $response->getCurrentStep(),
                'allow_back_navigation' => $questionnaire->isAllowBackNavigation(),
            ]);

            if ($step < 1 || $step > $totalSteps) {
                $this->logger->warning('Invalid step number requested', [
                    'token' => $token,
                    'requested_step' => $step,
                    'total_steps' => $totalSteps,
                    'valid_range' => "1-{$totalSteps}",
                ]);

                throw $this->createNotFoundException('Étape invalide.');
            }

            // Check if user can access this step
            if ($step > $response->getCurrentStep() && !$questionnaire->isAllowBackNavigation()) {
                $this->logger->warning('User trying to access future step without permission', [
                    'token' => $token,
                    'requested_step' => $step,
                    'current_step' => $response->getCurrentStep(),
                    'allow_back_navigation' => $questionnaire->isAllowBackNavigation(),
                ]);

                return $this->redirectToRoute('questionnaire_step', [
                    'token' => $token,
                    'step' => $response->getCurrentStep(),
                ]);
            }

            $questions = $questionnaire->getQuestionsForStep($step);

            $this->logger->info('Questions loaded for step', [
                'token' => $token,
                'step' => $step,
                'question_count' => count($questions),
                'question_ids' => array_map(static fn ($q) => $q->getId(), $questions->toArray()),
            ]);

            if ($request->isMethod('POST')) {
                $this->logger->info('Processing POST submission for step', [
                    'token' => $token,
                    'step' => $step,
                    'request_data_keys' => array_keys($request->request->all()),
                    'files_count' => count($request->files->all()),
                ]);

                try {
                    $this->handleStepSubmission($request, $response, $questions, $step);

                    // Update current step
                    $previousStep = $response->getCurrentStep();
                    $response->setCurrentStep(max($response->getCurrentStep(), $step));
                    $response->markAsInProgress();

                    $this->logger->info('Step submission processed successfully', [
                        'token' => $token,
                        'step' => $step,
                        'previous_step' => $previousStep,
                        'new_current_step' => $response->getCurrentStep(),
                        'status' => $response->getStatus(),
                    ]);

                    // Check if this is the last step
                    if ($step >= $totalSteps) {
                        $this->logger->info('Final step completed, marking questionnaire as completed', [
                            'token' => $token,
                            'step' => $step,
                            'total_steps' => $totalSteps,
                            'response_id' => $response->getId(),
                        ]);

                        $response->markAsCompleted();
                        $this->entityManager->flush();

                        $this->logger->info('Questionnaire marked as completed successfully', [
                            'token' => $token,
                            'response_id' => $response->getId(),
                            'completed_at' => $response->getCompletedAt()?->format('Y-m-d H:i:s'),
                        ]);

                        return $this->redirectToRoute('questionnaire_completed', ['token' => $token]);
                    }

                    $this->entityManager->flush();

                    $this->logger->info('Redirecting to next step', [
                        'token' => $token,
                        'current_step' => $step,
                        'next_step' => $step + 1,
                        'total_steps' => $totalSteps,
                    ]);

                    // Redirect to next step
                    return $this->redirectToRoute('questionnaire_step', [
                        'token' => $token,
                        'step' => $step + 1,
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Error processing step submission', [
                        'token' => $token,
                        'step' => $step,
                        'error_message' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                    ]);
                    $this->addFlash('error', 'Erreur lors de la sauvegarde des réponses.');
                    // Continue to show the form again
                }
            }

            // Get existing responses for this step
            $existingResponses = [];
            $existingQuestionResponses = [];

            try {
                foreach ($questions as $question) {
                    $questionResponse = $response->getResponseForQuestion($question);
                    if ($questionResponse) {
                        $existingResponses[$question->getId()] = $questionResponse->getResponseValue();
                        $existingQuestionResponses[$question->getId()] = $questionResponse;
                    }
                }

                $this->logger->info('Existing responses loaded', [
                    'token' => $token,
                    'step' => $step,
                    'existing_responses_count' => count($existingResponses),
                    'question_ids_with_responses' => array_keys($existingResponses),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Error loading existing responses', [
                    'token' => $token,
                    'step' => $step,
                    'error_message' => $e->getMessage(),
                ]);
                $existingResponses = [];
                $existingQuestionResponses = [];
            }

            $progressPercentage = (int) (($step / $totalSteps) * 100);

            $this->logger->info('Rendering questionnaire step template', [
                'token' => $token,
                'step' => $step,
                'total_steps' => $totalSteps,
                'progress_percentage' => $progressPercentage,
                'questions_count' => count($questions),
                'existing_responses_count' => count($existingResponses),
            ]);

            return $this->render('public/questionnaire/step.html.twig', [
                'response' => $response,
                'questionnaire' => $questionnaire,
                'questions' => $questions,
                'currentStep' => $step,
                'totalSteps' => $totalSteps,
                'existingResponses' => $existingResponses,
                'existingQuestionResponses' => $existingQuestionResponses,
                'progressPercentage' => $progressPercentage,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Critical error in questionnaire step processing', [
                'token' => $token,
                'step' => $step,
                'method' => $request->getMethod(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            if ($e instanceof NotFoundHttpException) {
                throw $e;
            }

            $this->addFlash('error', 'Une erreur est survenue lors du traitement de l\'étape.');

            throw $e;
        }
    }

    #[Route('/completed/{token}', name: 'questionnaire_completed', methods: ['GET'])]
    public function completed(string $token): Response
    {
        $this->logger->info('Accessing questionnaire completion page', [
            'token' => $token,
            'method' => 'completed',
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);

        try {
            $response = $this->responseRepository->findByToken($token);

            if (!$response) {
                $this->logger->warning('Invalid token for completion page', [
                    'token' => $token,
                    'action' => 'completion_token_validation_failed',
                ]);

                throw $this->createNotFoundException('Token de questionnaire invalide.');
            }

            $this->logger->info('Questionnaire response found for completion', [
                'token' => $token,
                'response_id' => $response->getId(),
                'questionnaire_id' => $response->getQuestionnaire()->getId(),
                'questionnaire_title' => $response->getQuestionnaire()->getTitle(),
                'is_completed' => $response->isCompleted(),
                'completed_at' => $response->getCompletedAt()?->format('Y-m-d H:i:s'),
                'status' => $response->getStatus(),
            ]);

            if (!$response->isCompleted()) {
                $this->logger->warning('Accessing completion page for incomplete questionnaire', [
                    'token' => $token,
                    'response_id' => $response->getId(),
                    'status' => $response->getStatus(),
                    'current_step' => $response->getCurrentStep(),
                ]);

                return $this->redirectToRoute('questionnaire_complete', ['token' => $token]);
            }

            $this->logger->info('Rendering questionnaire completion page', [
                'token' => $token,
                'response_id' => $response->getId(),
                'questionnaire_title' => $response->getQuestionnaire()->getTitle(),
                'total_responses' => count($response->getQuestionResponses()),
            ]);

            return $this->render('public/questionnaire/completed.html.twig', [
                'response' => $response,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error in questionnaire completion page', [
                'token' => $token,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            if ($e instanceof NotFoundHttpException) {
                throw $e;
            }

            $this->addFlash('error', 'Une erreur est survenue lors de l\'affichage de la page de completion.');

            throw $e;
        }
    }

    #[Route('/upload-file', name: 'questionnaire_upload_file', methods: ['POST'])]
    public function uploadFile(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        $questionId = $request->request->get('question_id');
        $token = $request->request->get('token');

        $this->logger->info('Starting file upload for questionnaire', [
            'token' => $token,
            'question_id' => $questionId,
            'file_provided' => $file instanceof UploadedFile,
            'original_filename' => $file instanceof UploadedFile ? $file->getClientOriginalName() : null,
            'file_size' => $file instanceof UploadedFile ? $file->getSize() : null,
            'file_mime_type' => $file instanceof UploadedFile ? $file->getMimeType() : null,
            'user_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);

        try {
            if (!$file instanceof UploadedFile) {
                $this->logger->warning('File upload failed - no file provided', [
                    'token' => $token,
                    'question_id' => $questionId,
                ]);

                return new JsonResponse(['success' => false, 'message' => 'Aucun fichier fourni']);
            }

            $response = $this->responseRepository->findByToken($token);
            if (!$response) {
                $this->logger->warning('File upload failed - invalid token', [
                    'token' => $token,
                    'question_id' => $questionId,
                    'filename' => $file->getClientOriginalName(),
                ]);

                return new JsonResponse(['success' => false, 'message' => 'Token invalide']);
            }

            $this->logger->info('Token validated for file upload', [
                'token' => $token,
                'response_id' => $response->getId(),
                'questionnaire_id' => $response->getQuestionnaire()->getId(),
            ]);

            $question = $this->entityManager->getRepository(Question::class)->find($questionId);
            if (!$question || $question->getQuestionnaire() !== $response->getQuestionnaire()) {
                $this->logger->warning('File upload failed - invalid question', [
                    'token' => $token,
                    'question_id' => $questionId,
                    'question_found' => $question !== null,
                    'questionnaire_match' => $question ? ($question->getQuestionnaire() === $response->getQuestionnaire()) : false,
                    'filename' => $file->getClientOriginalName(),
                ]);

                return new JsonResponse(['success' => false, 'message' => 'Question invalide']);
            }

            $this->logger->info('Question validated for file upload', [
                'token' => $token,
                'question_id' => $questionId,
                'question_type' => $question->getType(),
                'question_title' => $question->getTitle(),
                'allowed_file_types' => $question->getAllowedFileTypes(),
                'max_file_size' => $question->getMaxFileSize(),
            ]);

            // Validate file type and size
            if ($question->getAllowedFileTypes()) {
                $extension = $file->getClientOriginalExtension();
                if (!in_array($extension, $question->getAllowedFileTypes(), true)) {
                    $this->logger->warning('File upload failed - invalid file type', [
                        'token' => $token,
                        'question_id' => $questionId,
                        'filename' => $file->getClientOriginalName(),
                        'file_extension' => $extension,
                        'allowed_types' => $question->getAllowedFileTypes(),
                    ]);

                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Type de fichier non autorisé. Types autorisés : ' . implode(', ', $question->getAllowedFileTypes()),
                    ]);
                }
            }

            if ($question->getMaxFileSize() && $file->getSize() > $question->getMaxFileSize()) {
                $this->logger->warning('File upload failed - file too large', [
                    'token' => $token,
                    'question_id' => $questionId,
                    'filename' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'max_allowed_size' => $question->getMaxFileSize(),
                    'formatted_max_size' => $question->getFormattedMaxFileSize(),
                ]);

                return new JsonResponse([
                    'success' => false,
                    'message' => 'Fichier trop volumineux. Taille maximale : ' . $question->getFormattedMaxFileSize(),
                ]);
            }

            // Create upload directory if it doesn't exist
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/questionnaire_files';

            $this->logger->info('Preparing file upload directory', [
                'token' => $token,
                'question_id' => $questionId,
                'upload_dir' => $uploadDir,
                'dir_exists' => is_dir($uploadDir),
                'dir_writable' => is_dir($uploadDir) ? is_writable($uploadDir) : null,
            ]);

            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    $this->logger->error('Failed to create upload directory', [
                        'token' => $token,
                        'question_id' => $questionId,
                        'upload_dir' => $uploadDir,
                    ]);

                    return new JsonResponse(['success' => false, 'message' => 'Erreur lors de la création du dossier de téléchargement']);
                }

                $this->logger->info('Upload directory created successfully', [
                    'token' => $token,
                    'upload_dir' => $uploadDir,
                ]);
            }

            // Generate unique filename
            $filename = uniqid() . '_' . $file->getClientOriginalName();
            $fullPath = $uploadDir . '/' . $filename;

            $this->logger->info('Moving uploaded file', [
                'token' => $token,
                'question_id' => $questionId,
                'original_filename' => $file->getClientOriginalName(),
                'generated_filename' => $filename,
                'full_path' => $fullPath,
            ]);

            $file->move($uploadDir, $filename);

            $this->logger->info('File uploaded successfully', [
                'token' => $token,
                'question_id' => $questionId,
                'original_filename' => $file->getClientOriginalName(),
                'generated_filename' => $filename,
                'file_size' => filesize($fullPath),
                'upload_timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
            ]);

            return new JsonResponse([
                'success' => true,
                'filename' => $filename,
                'originalName' => $file->getClientOriginalName(),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Critical error during file upload', [
                'token' => $token,
                'question_id' => $questionId,
                'filename' => $file instanceof UploadedFile ? $file->getClientOriginalName() : null,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse(['success' => false, 'message' => 'Erreur lors du téléchargement']);
        }
    }

    private function handleStepSubmission(Request $request, QuestionnaireResponse $response, $questions, int $step): void
    {
        $data = $request->request->all();

        $this->logger->info('Starting step submission processing', [
            'response_id' => $response->getId(),
            'questionnaire_id' => $response->getQuestionnaire()->getId(),
            'step' => $step,
            'questions_count' => count($questions),
            'request_data_keys' => array_keys($data),
            'has_file_data' => !empty($request->files->all()),
        ]);

        $processedQuestions = 0;
        $erroredQuestions = 0;
        $skippedQuestions = 0;

        foreach ($questions as $question) {
            $questionId = $question->getId();
            $responseValue = $data['question_' . $questionId] ?? null;

            $this->logger->debug('Processing question submission', [
                'response_id' => $response->getId(),
                'step' => $step,
                'question_id' => $questionId,
                'question_type' => $question->getType(),
                'question_title' => $question->getTitle(),
                'is_required' => $question->isRequired(),
                'has_response_value' => !empty($responseValue),
                'response_value_type' => gettype($responseValue),
                'response_value_length' => is_string($responseValue) ? strlen($responseValue) : null,
            ]);

            // Skip if required question is empty
            if ($question->isRequired() && empty($responseValue)) {
                $this->logger->warning('Required question has empty response', [
                    'response_id' => $response->getId(),
                    'step' => $step,
                    'question_id' => $questionId,
                    'question_title' => $question->getTitle(),
                    'question_type' => $question->getType(),
                ]);
                $skippedQuestions++;

                continue;
            }

            try {
                // Find or create question response
                $questionResponse = $response->getResponseForQuestion($question);
                $isNewResponse = !$questionResponse;

                if (!$questionResponse) {
                    $questionResponse = new QuestionResponse();
                    $questionResponse->setQuestion($question)
                        ->setQuestionnaireResponse($response)
                    ;
                    $this->entityManager->persist($questionResponse);
                    $response->addQuestionResponse($questionResponse);

                    $this->logger->info('Created new question response', [
                        'response_id' => $response->getId(),
                        'question_id' => $questionId,
                        'step' => $step,
                    ]);
                } else {
                    $this->logger->info('Updating existing question response', [
                        'response_id' => $response->getId(),
                        'question_id' => $questionId,
                        'question_response_id' => $questionResponse->getId(),
                        'step' => $step,
                    ]);
                }

                // Set response value based on question type
                switch ($question->getType()) {
                    case Question::TYPE_SINGLE_CHOICE:
                        if ($responseValue) {
                            $choiceValue = (int) $responseValue;
                            $questionResponse->setChoiceResponse([$choiceValue]);

                            $this->logger->info('Set single choice response', [
                                'response_id' => $response->getId(),
                                'question_id' => $questionId,
                                'choice_value' => $choiceValue,
                            ]);
                        }
                        break;

                    case Question::TYPE_MULTIPLE_CHOICE:
                        if (is_array($responseValue)) {
                            $choiceValues = array_map('intval', $responseValue);
                            $questionResponse->setChoiceResponse($choiceValues);

                            $this->logger->info('Set multiple choice response', [
                                'response_id' => $response->getId(),
                                'question_id' => $questionId,
                                'choice_values' => $choiceValues,
                                'choice_count' => count($choiceValues),
                            ]);
                        }
                        break;

                    case Question::TYPE_FILE_UPLOAD:
                        // File upload is handled via AJAX
                        $filename = $data['file_' . $questionId] ?? null;
                        if ($filename) {
                            $questionResponse->setFileResponse($filename);

                            $this->logger->info('Set file upload response', [
                                'response_id' => $response->getId(),
                                'question_id' => $questionId,
                                'filename' => $filename,
                            ]);
                        }
                        break;

                    case Question::TYPE_NUMBER:
                        if ($responseValue !== null && $responseValue !== '') {
                            $numberValue = (int) $responseValue;
                            $questionResponse->setNumberResponse($numberValue);

                            $this->logger->info('Set number response', [
                                'response_id' => $response->getId(),
                                'question_id' => $questionId,
                                'number_value' => $numberValue,
                                'original_value' => $responseValue,
                            ]);
                        }
                        break;

                    case Question::TYPE_DATE:
                        if ($responseValue) {
                            try {
                                $dateValue = new DateTime($responseValue);
                                $questionResponse->setDateResponse($dateValue);

                                $this->logger->info('Set date response', [
                                    'response_id' => $response->getId(),
                                    'question_id' => $questionId,
                                    'date_value' => $dateValue->format('Y-m-d'),
                                    'original_value' => $responseValue,
                                ]);
                            } catch (Exception $e) {
                                $this->logger->error('Invalid date format in response', [
                                    'response_id' => $response->getId(),
                                    'question_id' => $questionId,
                                    'invalid_date' => $responseValue,
                                    'error_message' => $e->getMessage(),
                                ]);
                                // Invalid date, skip
                            }
                        }
                        break;

                    default:
                        $questionResponse->setTextResponse($responseValue);

                        $this->logger->info('Set text response', [
                            'response_id' => $response->getId(),
                            'question_id' => $questionId,
                            'question_type' => $question->getType(),
                            'text_length' => is_string($responseValue) ? strlen($responseValue) : null,
                        ]);
                }

                // Calculate score for choice questions
                if ($question->hasChoices() && $question->hasCorrectAnswers()) {
                    $previousScore = $questionResponse->getScoreEarned();
                    $calculatedScore = $questionResponse->calculateScore();

                    $this->logger->info('Calculated question score', [
                        'response_id' => $response->getId(),
                        'question_id' => $questionId,
                        'previous_score' => $previousScore,
                        'calculated_score' => $calculatedScore,
                        'has_correct_answers' => $question->hasCorrectAnswers(),
                    ]);
                }

                $processedQuestions++;
            } catch (Exception $e) {
                $this->logger->error('Error processing individual question response', [
                    'response_id' => $response->getId(),
                    'step' => $step,
                    'question_id' => $questionId,
                    'question_type' => $question->getType(),
                    'question_title' => $question->getTitle(),
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine(),
                ]);
                $erroredQuestions++;
            }
        }

        $this->logger->info('Completed step submission processing', [
            'response_id' => $response->getId(),
            'step' => $step,
            'total_questions' => count($questions),
            'processed_questions' => $processedQuestions,
            'errored_questions' => $erroredQuestions,
            'skipped_questions' => $skippedQuestions,
        ]);

        if ($erroredQuestions > 0) {
            $this->logger->warning('Some questions had processing errors during submission', [
                'response_id' => $response->getId(),
                'step' => $step,
                'errored_questions_count' => $erroredQuestions,
                'total_questions' => count($questions),
            ]);
        }
    }
}
