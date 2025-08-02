<?php

declare(strict_types=1);

namespace App\Controller\Admin\Assessment;

use App\Entity\Assessment\Question;
use App\Entity\Assessment\Questionnaire;
use App\Entity\Assessment\QuestionOption;
use App\Repository\Assessment\QuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Admin controller for managing questionnaire questions.
 */
#[Route('/admin/questionnaires/{questionnaireId}/questions')]
class QuestionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private QuestionRepository $questionRepository,
        private LoggerInterface $logger,
    ) {}

    #[Route('', name: 'admin_question_index', methods: ['GET'])]
    public function index(int $questionnaireId): Response
    {
        $userId = $this->getUser() ? $this->getUser()->getUserIdentifier() : null;
        
        $this->logger->info('Accessing question index page', [
            'questionnaire_id' => $questionnaireId,
            'user_id' => $userId,
        ]);

        try {
            $questionnaire = $this->entityManager->getRepository(Questionnaire::class)->find($questionnaireId);

            if (!$questionnaire) {
                $this->logger->warning('Questionnaire not found for question index', [
                    'questionnaire_id' => $questionnaireId,
                    'user_id' => $userId,
                ]);
                throw $this->createNotFoundException('Questionnaire not found');
            }

            $questions = $this->questionRepository->findByQuestionnaireWithOptions($questionnaire);

            $this->logger->info('Successfully loaded questions for questionnaire', [
                'questionnaire_id' => $questionnaireId,
                'questions_count' => count($questions),
                'user_id' => $userId,
            ]);

            return $this->render('admin/question/index.html.twig', [
                'questionnaire' => $questionnaire,
                'questions' => $questions,
                'question_types' => Question::TYPES,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error loading question index page', [
                'questionnaire_id' => $questionnaireId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $userId,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des questions.');
            throw $e;
        }
    }

    #[Route('/new', name: 'admin_question_new', methods: ['GET', 'POST'])]
    public function new(Request $request, int $questionnaireId): Response
    {
        $userId = $this->getUser() ? $this->getUser()->getUserIdentifier() : null;
        
        $this->logger->info('Accessing new question form', [
            'questionnaire_id' => $questionnaireId,
            'method' => $request->getMethod(),
            'user_id' => $userId,
        ]);

        try {
            $questionnaire = $this->entityManager->getRepository(Questionnaire::class)->find($questionnaireId);

            if (!$questionnaire) {
                $this->logger->warning('Questionnaire not found for new question', [
                    'questionnaire_id' => $questionnaireId,
                    'user_id' => $userId,
                ]);
                throw $this->createNotFoundException('Questionnaire not found');
            }

            $question = new Question();
            $question->setQuestionnaire($questionnaire);
            
            try {
                $nextOrderIndex = $this->questionRepository->getNextOrderIndex($questionnaire);
                $question->setOrderIndex($nextOrderIndex);
                
                $this->logger->debug('Set next order index for new question', [
                    'questionnaire_id' => $questionnaireId,
                    'next_order_index' => $nextOrderIndex,
                    'user_id' => $userId,
                ]);
            } catch (Exception $e) {
                $this->logger->error('Error getting next order index for new question', [
                    'questionnaire_id' => $questionnaireId,
                    'error' => $e->getMessage(),
                    'user_id' => $userId,
                ]);
                // Set default order index
                $question->setOrderIndex(1);
            }

            if ($request->isMethod('POST')) {
                $this->logger->info('Processing new question form submission', [
                    'questionnaire_id' => $questionnaireId,
                    'user_id' => $userId,
                ]);

                try {
                    $this->handleQuestionForm($request, $question);

                    $this->logger->debug('Question form data processed successfully', [
                        'questionnaire_id' => $questionnaireId,
                        'question_text' => $question->getQuestionText(),
                        'question_type' => $question->getType(),
                        'is_required' => $question->isRequired(),
                        'user_id' => $userId,
                    ]);

                    $this->entityManager->persist($question);
                    $this->entityManager->flush();

                    $this->logger->info('New question created successfully', [
                        'questionnaire_id' => $questionnaireId,
                        'question_id' => $question->getId(),
                        'question_text' => $question->getQuestionText(),
                        'question_type' => $question->getType(),
                        'user_id' => $userId,
                    ]);

                    $this->addFlash('success', 'La question a été créée avec succès.');

                    return $this->redirectToRoute('admin_question_index', ['questionnaireId' => $questionnaireId]);
                } catch (Exception $e) {
                    $this->logger->error('Error creating new question', [
                        'questionnaire_id' => $questionnaireId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'request_data' => $request->request->all(),
                        'user_id' => $userId,
                    ]);

                    $this->addFlash('error', 'Une erreur est survenue lors de la création de la question: ' . $e->getMessage());
                }
            }

            return $this->render('admin/question/new.html.twig', [
                'questionnaire' => $questionnaire,
                'question' => $question,
                'question_types' => Question::TYPES,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error in new question controller', [
                'questionnaire_id' => $questionnaireId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $userId,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'accès à la création de question.');
            throw $e;
        }
    }

    #[Route('/{id}/edit', name: 'admin_question_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $questionnaireId, Question $question): Response
    {
        $userId = $this->getUser() ? $this->getUser()->getUserIdentifier() : null;
        
        $this->logger->info('Accessing edit question form', [
            'questionnaire_id' => $questionnaireId,
            'question_id' => $question->getId(),
            'method' => $request->getMethod(),
            'user_id' => $userId,
        ]);

        try {
            $questionnaire = $question->getQuestionnaire();

            if ($questionnaire->getId() !== $questionnaireId) {
                $this->logger->warning('Question not found in questionnaire for edit', [
                    'questionnaire_id' => $questionnaireId,
                    'question_id' => $question->getId(),
                    'actual_questionnaire_id' => $questionnaire->getId(),
                    'user_id' => $userId,
                ]);
                throw $this->createNotFoundException('Question not found in this questionnaire');
            }

            if ($request->isMethod('POST')) {
                $this->logger->info('Processing edit question form submission', [
                    'questionnaire_id' => $questionnaireId,
                    'question_id' => $question->getId(),
                    'user_id' => $userId,
                ]);

                try {
                    $originalData = [
                        'question_text' => $question->getQuestionText(),
                        'type' => $question->getType(),
                        'is_required' => $question->isRequired(),
                        'is_active' => $question->isActive(),
                        'options_count' => $question->getOptions()->count(),
                    ];

                    $this->handleQuestionForm($request, $question);

                    $updatedData = [
                        'question_text' => $question->getQuestionText(),
                        'type' => $question->getType(),
                        'is_required' => $question->isRequired(),
                        'is_active' => $question->isActive(),
                        'options_count' => $question->getOptions()->count(),
                    ];

                    $this->logger->debug('Question form data processed for edit', [
                        'questionnaire_id' => $questionnaireId,
                        'question_id' => $question->getId(),
                        'original_data' => $originalData,
                        'updated_data' => $updatedData,
                        'user_id' => $userId,
                    ]);

                    $this->entityManager->flush();

                    $this->logger->info('Question updated successfully', [
                        'questionnaire_id' => $questionnaireId,
                        'question_id' => $question->getId(),
                        'changes' => array_diff_assoc($updatedData, $originalData),
                        'user_id' => $userId,
                    ]);

                    $this->addFlash('success', 'La question a été modifiée avec succès.');

                    return $this->redirectToRoute('admin_question_index', ['questionnaireId' => $questionnaireId]);
                } catch (Exception $e) {
                    $this->logger->error('Error updating question', [
                        'questionnaire_id' => $questionnaireId,
                        'question_id' => $question->getId(),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'request_data' => $request->request->all(),
                        'user_id' => $userId,
                    ]);

                    $this->addFlash('error', 'Une erreur est survenue lors de la modification de la question: ' . $e->getMessage());
                }
            }

            return $this->render('admin/question/edit.html.twig', [
                'questionnaire' => $questionnaire,
                'question' => $question,
                'question_types' => Question::TYPES,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error in edit question controller', [
                'questionnaire_id' => $questionnaireId,
                'question_id' => $question->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $userId,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'accès à la modification de question.');
            throw $e;
        }
    }

    #[Route('/{id}/delete', name: 'admin_question_delete', methods: ['POST'])]
    public function delete(Request $request, int $questionnaireId, Question $question): Response
    {
        $userId = $this->getUser() ? $this->getUser()->getUserIdentifier() : null;
        
        $this->logger->info('Attempting to delete question', [
            'questionnaire_id' => $questionnaireId,
            'question_id' => $question->getId(),
            'question_text' => $question->getQuestionText(),
            'user_id' => $userId,
        ]);

        try {
            if (!$this->isCsrfTokenValid('delete' . $question->getId(), $request->request->get('_token'))) {
                $this->logger->warning('Invalid CSRF token for question deletion', [
                    'questionnaire_id' => $questionnaireId,
                    'question_id' => $question->getId(),
                    'provided_token' => $request->request->get('_token'),
                    'user_id' => $userId,
                ]);

                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_question_index', ['questionnaireId' => $questionnaireId]);
            }

            $questionData = [
                'id' => $question->getId(),
                'text' => $question->getQuestionText(),
                'type' => $question->getType(),
                'options_count' => $question->getOptions()->count(),
                'order_index' => $question->getOrderIndex(),
            ];

            $this->entityManager->remove($question);
            $this->entityManager->flush();

            $this->logger->info('Question deleted successfully', [
                'questionnaire_id' => $questionnaireId,
                'deleted_question' => $questionData,
                'user_id' => $userId,
            ]);

            $this->addFlash('success', 'La question a été supprimée avec succès.');

            return $this->redirectToRoute('admin_question_index', ['questionnaireId' => $questionnaireId]);
        } catch (Exception $e) {
            $this->logger->error('Error deleting question', [
                'questionnaire_id' => $questionnaireId,
                'question_id' => $question->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $userId,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la suppression de la question: ' . $e->getMessage());
            return $this->redirectToRoute('admin_question_index', ['questionnaireId' => $questionnaireId]);
        }
    }

    #[Route('/{id}/duplicate', name: 'admin_question_duplicate', methods: ['POST'])]
    public function duplicate(Request $request, int $questionnaireId, Question $question): Response
    {
        $userId = $this->getUser() ? $this->getUser()->getUserIdentifier() : null;
        
        $this->logger->info('Attempting to duplicate question', [
            'questionnaire_id' => $questionnaireId,
            'question_id' => $question->getId(),
            'question_text' => $question->getQuestionText(),
            'user_id' => $userId,
        ]);

        try {
            if (!$this->isCsrfTokenValid('duplicate' . $question->getId(), $request->request->get('_token'))) {
                $this->logger->warning('Invalid CSRF token for question duplication', [
                    'questionnaire_id' => $questionnaireId,
                    'question_id' => $question->getId(),
                    'provided_token' => $request->request->get('_token'),
                    'user_id' => $userId,
                ]);

                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('admin_question_index', ['questionnaireId' => $questionnaireId]);
            }

            $originalQuestionData = [
                'id' => $question->getId(),
                'text' => $question->getQuestionText(),
                'type' => $question->getType(),
                'options_count' => $question->getOptions()->count(),
            ];

            $newQuestion = new Question();
            $newQuestion->setQuestionnaire($question->getQuestionnaire())
                ->setQuestionText($question->getQuestionText() . ' (Copie)')
                ->setType($question->getType())
                ->setOrderIndex($this->questionRepository->getNextOrderIndex($question->getQuestionnaire()))
                ->setIsRequired($question->isRequired())
                ->setIsActive($question->isActive())
                ->setHelpText($question->getHelpText())
                ->setPlaceholder($question->getPlaceholder())
                ->setMinLength($question->getMinLength())
                ->setMaxLength($question->getMaxLength())
                ->setValidationRules($question->getValidationRules())
                ->setAllowedFileTypes($question->getAllowedFileTypes())
                ->setMaxFileSize($question->getMaxFileSize())
                ->setPoints($question->getPoints())
            ;

            $this->logger->debug('Created duplicate question object', [
                'questionnaire_id' => $questionnaireId,
                'original_question_id' => $question->getId(),
                'new_question_text' => $newQuestion->getQuestionText(),
                'new_order_index' => $newQuestion->getOrderIndex(),
                'user_id' => $userId,
            ]);

            $this->entityManager->persist($newQuestion);

            // Duplicate options
            $duplicatedOptionsCount = 0;
            foreach ($question->getOptions() as $option) {
                $newOption = new QuestionOption();
                $newOption->setQuestion($newQuestion)
                    ->setOptionText($option->getOptionText())
                    ->setOrderIndex($option->getOrderIndex())
                    ->setIsCorrect($option->isCorrect())
                    ->setIsActive($option->isActive())
                    ->setPoints($option->getPoints())
                    ->setExplanation($option->getExplanation())
                ;

                $this->entityManager->persist($newOption);
                $duplicatedOptionsCount++;
            }

            $this->logger->debug('Duplicated question options', [
                'questionnaire_id' => $questionnaireId,
                'original_question_id' => $question->getId(),
                'duplicated_options_count' => $duplicatedOptionsCount,
                'user_id' => $userId,
            ]);

            $this->entityManager->flush();

            $this->logger->info('Question duplicated successfully', [
                'questionnaire_id' => $questionnaireId,
                'original_question' => $originalQuestionData,
                'new_question_id' => $newQuestion->getId(),
                'new_question_text' => $newQuestion->getQuestionText(),
                'duplicated_options_count' => $duplicatedOptionsCount,
                'user_id' => $userId,
            ]);

            $this->addFlash('success', 'La question a été dupliquée avec succès.');

            return $this->redirectToRoute('admin_question_index', ['questionnaireId' => $questionnaireId]);
        } catch (Exception $e) {
            $this->logger->error('Error duplicating question', [
                'questionnaire_id' => $questionnaireId,
                'question_id' => $question->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $userId,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la duplication de la question: ' . $e->getMessage());
            return $this->redirectToRoute('admin_question_index', ['questionnaireId' => $questionnaireId]);
        }
    }

    #[Route('/reorder', name: 'admin_question_reorder', methods: ['POST'])]
    public function reorder(Request $request, int $questionnaireId): JsonResponse
    {
        $userId = $this->getUser() ? $this->getUser()->getUserIdentifier() : null;
        
        $this->logger->info('Attempting to reorder questions', [
            'questionnaire_id' => $questionnaireId,
            'user_id' => $userId,
        ]);

        try {
            $questionIds = $request->request->get('questionIds', []);

            if (!is_array($questionIds)) {
                $this->logger->warning('Invalid question IDs provided for reordering', [
                    'questionnaire_id' => $questionnaireId,
                    'provided_data' => $questionIds,
                    'user_id' => $userId,
                ]);
                return new JsonResponse(['success' => false, 'message' => 'Invalid data']);
            }

            $questionnaire = $this->entityManager->getRepository(Questionnaire::class)->find($questionnaireId);

            if (!$questionnaire) {
                $this->logger->warning('Questionnaire not found for reordering', [
                    'questionnaire_id' => $questionnaireId,
                    'user_id' => $userId,
                ]);
                return new JsonResponse(['success' => false, 'message' => 'Questionnaire not found']);
            }

            $this->logger->debug('Processing question reordering', [
                'questionnaire_id' => $questionnaireId,
                'question_ids' => $questionIds,
                'total_questions' => count($questionIds),
                'user_id' => $userId,
            ]);

            $this->questionRepository->reorderQuestions($questionnaire, $questionIds);

            $this->logger->info('Questions reordered successfully', [
                'questionnaire_id' => $questionnaireId,
                'reordered_question_ids' => $questionIds,
                'user_id' => $userId,
            ]);

            return new JsonResponse(['success' => true]);
        } catch (Exception $e) {
            $this->logger->error('Error reordering questions', [
                'questionnaire_id' => $questionnaireId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->request->all(),
                'user_id' => $userId,
            ]);

            return new JsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    #[Route('/{id}/toggle-status', name: 'admin_question_toggle_status', methods: ['POST'])]
    public function toggleStatus(Request $request, int $questionnaireId, Question $question): JsonResponse
    {
        $userId = $this->getUser() ? $this->getUser()->getUserIdentifier() : null;
        
        $this->logger->info('Attempting to toggle question status', [
            'questionnaire_id' => $questionnaireId,
            'question_id' => $question->getId(),
            'current_status' => $question->isActive(),
            'user_id' => $userId,
        ]);

        try {
            if (!$this->isCsrfTokenValid('toggle_status' . $question->getId(), $request->request->get('_token'))) {
                $this->logger->warning('Invalid CSRF token for question status toggle', [
                    'questionnaire_id' => $questionnaireId,
                    'question_id' => $question->getId(),
                    'provided_token' => $request->request->get('_token'),
                    'user_id' => $userId,
                ]);
                return new JsonResponse(['success' => false, 'message' => 'Token CSRF invalide']);
            }

            $previousStatus = $question->isActive();
            $question->setIsActive(!$question->isActive());
            
            $this->entityManager->flush();

            $this->logger->info('Question status toggled successfully', [
                'questionnaire_id' => $questionnaireId,
                'question_id' => $question->getId(),
                'previous_status' => $previousStatus,
                'new_status' => $question->isActive(),
                'user_id' => $userId,
            ]);

            return new JsonResponse([
                'success' => true,
                'isActive' => $question->isActive(),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error toggling question status', [
                'questionnaire_id' => $questionnaireId,
                'question_id' => $question->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $userId,
            ]);

            return new JsonResponse([
                'success' => false, 
                'message' => 'Une erreur est survenue: ' . $e->getMessage()
            ]);
        }
    }

    private function handleQuestionForm(Request $request, Question $question): void
    {
        $userId = $this->getUser() ? $this->getUser()->getUserIdentifier() : null;
        
        $this->logger->debug('Starting question form handling', [
            'question_id' => $question->getId(),
            'question_text' => $question->getQuestionText(),
            'user_id' => $userId,
        ]);

        try {
            $data = $request->request->all();

            $this->logger->debug('Processing question form data', [
                'question_id' => $question->getId(),
                'form_data_keys' => array_keys($data),
                'question_text_provided' => !empty($data['questionText']),
                'type_provided' => !empty($data['type']),
                'user_id' => $userId,
            ]);

            $question->setQuestionText($data['questionText'] ?? '')
                ->setType($data['type'] ?? Question::TYPE_TEXT)
                ->setIsRequired(isset($data['isRequired']))
                ->setIsActive($data['isActive'] ?? true)
                ->setHelpText($data['helpText'] ?? null)
                ->setPlaceholder($data['placeholder'] ?? null)
                ->setMinLength(($data['minLength'] ?? null) ? (int) $data['minLength'] : null)
                ->setMaxLength(($data['maxLength'] ?? null) ? (int) $data['maxLength'] : null)
                ->setMaxFileSize(($data['maxFileSize'] ?? null) ? (int) $data['maxFileSize'] : null)
                ->setPoints(($data['maxPoints'] ?? null) ? (int) $data['maxPoints'] : 0)
            ;

            $this->logger->debug('Basic question properties set', [
                'question_id' => $question->getId(),
                'question_text' => $question->getQuestionText(),
                'type' => $question->getType(),
                'is_required' => $question->isRequired(),
                'is_active' => $question->isActive(),
                'points' => $question->getPoints(),
                'user_id' => $userId,
            ]);

            // Handle allowed file types
            if (!empty($data['allowedFileTypes'])) {
                $allowedTypes = array_map('trim', explode(',', $data['allowedFileTypes']));
                $question->setAllowedFileTypes($allowedTypes);
                
                $this->logger->debug('Allowed file types set', [
                    'question_id' => $question->getId(),
                    'allowed_types' => $allowedTypes,
                    'user_id' => $userId,
                ]);
            } else {
                $question->setAllowedFileTypes(null);
            }

            // Handle validation rules
            $validationRules = [];
            if (!empty($data['validationPattern'])) {
                $validationRules['pattern'] = $data['validationPattern'];
            }
            if (!empty($data['validationMessage'])) {
                $validationRules['message'] = $data['validationMessage'];
            }
            $question->setValidationRules($validationRules ?: null);

            if (!empty($validationRules)) {
                $this->logger->debug('Validation rules set', [
                    'question_id' => $question->getId(),
                    'validation_rules' => $validationRules,
                    'user_id' => $userId,
                ]);
            }

            // Handle options for choice questions
            if (in_array($question->getType(), [Question::TYPE_SINGLE_CHOICE, Question::TYPE_MULTIPLE_CHOICE], true)) {
                $this->logger->debug('Processing options for choice question', [
                    'question_id' => $question->getId(),
                    'question_type' => $question->getType(),
                    'options_data_provided' => isset($data['options']),
                    'user_id' => $userId,
                ]);

                $this->handleQuestionOptions($data, $question);
            } else {
                // Remove existing options if question type changed
                $removedOptionsCount = $question->getOptions()->count();
                foreach ($question->getOptions() as $option) {
                    $this->entityManager->remove($option);
                }

                if ($removedOptionsCount > 0) {
                    $this->logger->debug('Removed options for non-choice question type', [
                        'question_id' => $question->getId(),
                        'question_type' => $question->getType(),
                        'removed_options_count' => $removedOptionsCount,
                        'user_id' => $userId,
                    ]);
                }
            }

            $this->logger->info('Question form handling completed successfully', [
                'question_id' => $question->getId(),
                'question_text' => $question->getQuestionText(),
                'question_type' => $question->getType(),
                'options_count' => $question->getOptions()->count(),
                'user_id' => $userId,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error handling question form', [
                'question_id' => $question->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'form_data' => $request->request->all(),
                'user_id' => $userId,
            ]);

            throw $e;
        }
    }

    private function handleQuestionOptions(array $data, Question $question): void
    {
        $userId = $this->getUser() ? $this->getUser()->getUserIdentifier() : null;
        
        $this->logger->debug('Starting question options handling', [
            'question_id' => $question->getId(),
            'existing_options_count' => $question->getOptions()->count(),
            'user_id' => $userId,
        ]);

        try {
            // Get existing options
            $existingOptions = $question->getOptions()->toArray();
            $existingOptionIds = array_map(static fn ($option) => $option->getId(), $existingOptions);

            $this->logger->debug('Existing options analysis', [
                'question_id' => $question->getId(),
                'existing_option_ids' => $existingOptionIds,
                'user_id' => $userId,
            ]);

            $submittedOptionIds = [];
            $processedOptionsCount = 0;
            $newOptionsCount = 0;
            $updatedOptionsCount = 0;

            // Process submitted options
            if (isset($data['options']) && is_array($data['options'])) {
                $this->logger->debug('Processing submitted options', [
                    'question_id' => $question->getId(),
                    'submitted_options_count' => count($data['options']),
                    'user_id' => $userId,
                ]);

                foreach ($data['options'] as $index => $optionData) {
                    try {
                        $optionId = $optionData['id'] ?? null;
                        $optionText = trim($optionData['text'] ?? '');

                        if (empty($optionText)) {
                            $this->logger->debug('Skipping empty option text', [
                                'question_id' => $question->getId(),
                                'option_index' => $index,
                                'user_id' => $userId,
                            ]);
                            continue;
                        }

                        if ($optionId && in_array($optionId, $existingOptionIds, true)) {
                            // Update existing option
                            $option = $this->entityManager->getRepository(QuestionOption::class)->find($optionId);
                            $submittedOptionIds[] = $optionId;
                            $updatedOptionsCount++;

                            $this->logger->debug('Updating existing option', [
                                'question_id' => $question->getId(),
                                'option_id' => $optionId,
                                'option_text' => $optionText,
                                'user_id' => $userId,
                            ]);
                        } else {
                            // Create new option
                            $option = new QuestionOption();
                            $option->setQuestion($question);
                            $this->entityManager->persist($option);
                            $newOptionsCount++;

                            $this->logger->debug('Creating new option', [
                                'question_id' => $question->getId(),
                                'option_text' => $optionText,
                                'option_index' => $index + 1,
                                'user_id' => $userId,
                            ]);
                        }

                        $option->setOptionText($optionText)
                            ->setOrderIndex($index + 1)
                            ->setIsCorrect(isset($optionData['isCorrect']))
                            ->setIsActive($optionData['isActive'] ?? true)
                            ->setPoints(($optionData['points'] ?? null) ? (int) $optionData['points'] : 0)
                            ->setExplanation($optionData['explanation'] ?? null)
                        ;

                        $processedOptionsCount++;
                    } catch (Exception $e) {
                        $this->logger->error('Error processing individual option', [
                            'question_id' => $question->getId(),
                            'option_index' => $index,
                            'option_data' => $optionData,
                            'error' => $e->getMessage(),
                            'user_id' => $userId,
                        ]);
                        throw $e;
                    }
                }
            }

            // Remove options that were not submitted
            $removedOptionsCount = 0;
            foreach ($existingOptions as $option) {
                if ($option->getId() && !in_array($option->getId(), $submittedOptionIds, true)) {
                    $this->logger->debug('Removing option not in submission', [
                        'question_id' => $question->getId(),
                        'option_id' => $option->getId(),
                        'option_text' => $option->getOptionText(),
                        'user_id' => $userId,
                    ]);

                    $this->entityManager->remove($option);
                    $removedOptionsCount++;
                }
            }

            $this->logger->info('Question options handling completed successfully', [
                'question_id' => $question->getId(),
                'processed_options' => $processedOptionsCount,
                'new_options' => $newOptionsCount,
                'updated_options' => $updatedOptionsCount,
                'removed_options' => $removedOptionsCount,
                'final_options_count' => $processedOptionsCount,
                'user_id' => $userId,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error handling question options', [
                'question_id' => $question->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'options_data' => $data['options'] ?? null,
                'user_id' => $userId,
            ]);

            throw $e;
        }
    }
}
