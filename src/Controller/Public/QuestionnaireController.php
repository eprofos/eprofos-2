<?php

namespace App\Controller\Public;

use App\Entity\Assessment\Questionnaire;
use App\Entity\Assessment\QuestionnaireResponse;
use App\Entity\Assessment\QuestionResponse;
use App\Repository\QuestionnaireRepository;
use App\Repository\QuestionnaireResponseRepository;
use App\Repository\FormationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Public controller for questionnaire completion
 */
#[Route('/questionnaire', name: 'questionnaire_')]
class QuestionnaireController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private QuestionnaireRepository $questionnaireRepository,
        private QuestionnaireResponseRepository $responseRepository,
        private FormationRepository $formationRepository
    ) {
    }

    #[Route('/complete/{token}', name: 'complete', methods: ['GET'])]
    public function complete(string $token): Response
    {
        $response = $this->responseRepository->findByToken($token);
        
        if (!$response) {
            throw $this->createNotFoundException('Token de questionnaire invalide.');
        }

        if ($response->isCompleted()) {
            return $this->render('public/questionnaire/already_completed.html.twig', [
                'response' => $response
            ]);
        }

        $questionnaire = $response->getQuestionnaire();
        
        if (!$questionnaire->isActive()) {
            throw $this->createNotFoundException('Ce questionnaire n\'est plus actif.');
        }

        // Mark as started if not already
        if ($response->getStatus() === QuestionnaireResponse::STATUS_STARTED && !$response->getStartedAt()) {
            $response->markAsStarted();
            $this->entityManager->flush();
        }

        $currentStep = $response->getCurrentStep() ?? 1;
        $totalSteps = $questionnaire->getStepCount();
        
        // Redirect to the current step
        return $this->redirectToRoute('questionnaire_step', [
            'token' => $token,
            'step' => $currentStep
        ]);
    }

    #[Route('/step/{token}/{step}', name: 'step', methods: ['GET', 'POST'], requirements: ['step' => '\d+'])]
    public function step(Request $request, string $token, int $step): Response
    {
        $response = $this->responseRepository->findByToken($token);
        
        if (!$response) {
            throw $this->createNotFoundException('Token de questionnaire invalide.');
        }

        if ($response->isCompleted()) {
            return $this->redirectToRoute('questionnaire_complete', ['token' => $token]);
        }

        $questionnaire = $response->getQuestionnaire();
        $totalSteps = $questionnaire->getStepCount();

        if ($step < 1 || $step > $totalSteps) {
            throw $this->createNotFoundException('Étape invalide.');
        }

        // Check if user can access this step
        if ($step > $response->getCurrentStep() && !$questionnaire->isAllowBackNavigation()) {
            return $this->redirectToRoute('questionnaire_step', [
                'token' => $token,
                'step' => $response->getCurrentStep()
            ]);
        }

        $questions = $questionnaire->getQuestionsForStep($step);

        if ($request->isMethod('POST')) {
            $this->handleStepSubmission($request, $response, $questions, $step);
            
            // Update current step
            $response->setCurrentStep(max($response->getCurrentStep(), $step));
            $response->markAsInProgress();
            
            // Check if this is the last step
            if ($step >= $totalSteps) {
                $response->markAsCompleted();
                $this->entityManager->flush();
                
                return $this->redirectToRoute('questionnaire_completed', ['token' => $token]);
            } else {
                $this->entityManager->flush();
                
                // Redirect to next step
                return $this->redirectToRoute('questionnaire_step', [
                    'token' => $token,
                    'step' => $step + 1
                ]);
            }
        }

        // Get existing responses for this step
        $existingResponses = [];
        foreach ($questions as $question) {
            $questionResponse = $response->getResponseForQuestion($question);
            if ($questionResponse) {
                $existingResponses[$question->getId()] = $questionResponse->getResponseValue();
            }
        }

        return $this->render('public/questionnaire/step.html.twig', [
            'response' => $response,
            'questionnaire' => $questionnaire,
            'questions' => $questions,
            'currentStep' => $step,
            'totalSteps' => $totalSteps,
            'existingResponses' => $existingResponses,
            'progressPercentage' => (int) (($step / $totalSteps) * 100)
        ]);
    }

    #[Route('/completed/{token}', name: 'completed', methods: ['GET'])]
    public function completed(string $token): Response
    {
        $response = $this->responseRepository->findByToken($token);
        
        if (!$response) {
            throw $this->createNotFoundException('Token de questionnaire invalide.');
        }

        if (!$response->isCompleted()) {
            return $this->redirectToRoute('questionnaire_complete', ['token' => $token]);
        }

        return $this->render('public/questionnaire/completed.html.twig', [
            'response' => $response
        ]);
    }

    #[Route('/upload-file', name: 'upload_file', methods: ['POST'])]
    public function uploadFile(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        $questionId = $request->request->get('question_id');
        $token = $request->request->get('token');

        if (!$file instanceof UploadedFile) {
            return new JsonResponse(['success' => false, 'message' => 'Aucun fichier fourni']);
        }

        $response = $this->responseRepository->findByToken($token);
        if (!$response) {
            return new JsonResponse(['success' => false, 'message' => 'Token invalide']);
        }

        $question = $this->entityManager->getRepository(\App\Entity\Question::class)->find($questionId);
        if (!$question || $question->getQuestionnaire() !== $response->getQuestionnaire()) {
            return new JsonResponse(['success' => false, 'message' => 'Question invalide']);
        }

        // Validate file type and size
        if ($question->getAllowedFileTypes()) {
            $extension = $file->getClientOriginalExtension();
            if (!in_array($extension, $question->getAllowedFileTypes())) {
                return new JsonResponse([
                    'success' => false, 
                    'message' => 'Type de fichier non autorisé. Types autorisés : ' . implode(', ', $question->getAllowedFileTypes())
                ]);
            }
        }

        if ($question->getMaxFileSize() && $file->getSize() > $question->getMaxFileSize()) {
            return new JsonResponse([
                'success' => false, 
                'message' => 'Fichier trop volumineux. Taille maximale : ' . $question->getFormattedMaxFileSize()
            ]);
        }

        try {
            // Create upload directory if it doesn't exist
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/questionnaire_files';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Generate unique filename
            $filename = uniqid() . '_' . $file->getClientOriginalName();
            $file->move($uploadDir, $filename);

            return new JsonResponse([
                'success' => true,
                'filename' => $filename,
                'originalName' => $file->getClientOriginalName()
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Erreur lors du téléchargement']);
        }
    }

    private function handleStepSubmission(Request $request, QuestionnaireResponse $response, $questions, int $step): void
    {
        $data = $request->request->all();

        foreach ($questions as $question) {
            $questionId = $question->getId();
            $responseValue = $data['question_' . $questionId] ?? null;

            // Skip if required question is empty
            if ($question->isRequired() && empty($responseValue)) {
                continue;
            }

            // Find or create question response
            $questionResponse = $response->getResponseForQuestion($question);
            if (!$questionResponse) {
                $questionResponse = new QuestionResponse();
                $questionResponse->setQuestion($question)
                    ->setQuestionnaireResponse($response);
                $this->entityManager->persist($questionResponse);
                $response->addQuestionResponse($questionResponse);
            }

            // Set response value based on question type
            switch ($question->getType()) {
                case \App\Entity\Question::TYPE_SINGLE_CHOICE:
                    if ($responseValue) {
                        $questionResponse->setChoiceResponse([(int) $responseValue]);
                    }
                    break;

                case \App\Entity\Question::TYPE_MULTIPLE_CHOICE:
                    if (is_array($responseValue)) {
                        $questionResponse->setChoiceResponse(array_map('intval', $responseValue));
                    }
                    break;

                case \App\Entity\Question::TYPE_FILE_UPLOAD:
                    // File upload is handled via AJAX
                    $filename = $data['file_' . $questionId] ?? null;
                    if ($filename) {
                        $questionResponse->setFileResponse($filename);
                    }
                    break;

                case \App\Entity\Question::TYPE_NUMBER:
                    if ($responseValue !== null && $responseValue !== '') {
                        $questionResponse->setNumberResponse((int) $responseValue);
                    }
                    break;

                case \App\Entity\Question::TYPE_DATE:
                    if ($responseValue) {
                        try {
                            $questionResponse->setDateResponse(new \DateTime($responseValue));
                        } catch (\Exception $e) {
                            // Invalid date, skip
                        }
                    }
                    break;

                default:
                    $questionResponse->setTextResponse($responseValue);
            }

            // Calculate score for choice questions
            if ($question->hasChoices() && $question->hasCorrectAnswers()) {
                $questionResponse->calculateScore();
            }
        }
    }
}
