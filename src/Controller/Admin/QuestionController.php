<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Assessment\Question;
use App\Entity\Assessment\Questionnaire;
use App\Entity\Assessment\QuestionOption;
use App\Repository\Assessment\QuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Admin controller for managing questionnaire questions.
 */
#[Route('/admin/questionnaires/{questionnaireId}/questions', name: 'admin_question_')]
class QuestionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private QuestionRepository $questionRepository,
    ) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(int $questionnaireId): Response
    {
        $questionnaire = $this->entityManager->getRepository(Questionnaire::class)->find($questionnaireId);

        if (!$questionnaire) {
            throw $this->createNotFoundException('Questionnaire not found');
        }

        $questions = $this->questionRepository->findByQuestionnaireWithOptions($questionnaire);

        return $this->render('admin/question/index.html.twig', [
            'questionnaire' => $questionnaire,
            'questions' => $questions,
            'question_types' => Question::TYPES,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, int $questionnaireId): Response
    {
        $questionnaire = $this->entityManager->getRepository(Questionnaire::class)->find($questionnaireId);

        if (!$questionnaire) {
            throw $this->createNotFoundException('Questionnaire not found');
        }

        $question = new Question();
        $question->setQuestionnaire($questionnaire);
        $question->setOrderIndex($this->questionRepository->getNextOrderIndex($questionnaire));

        if ($request->isMethod('POST')) {
            $this->handleQuestionForm($request, $question);

            $this->entityManager->persist($question);
            $this->entityManager->flush();

            $this->addFlash('success', 'La question a été créée avec succès.');

            return $this->redirectToRoute('admin_question_index', ['questionnaireId' => $questionnaireId]);
        }

        return $this->render('admin/question/new.html.twig', [
            'questionnaire' => $questionnaire,
            'question' => $question,
            'question_types' => Question::TYPES,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $questionnaireId, Question $question): Response
    {
        $questionnaire = $question->getQuestionnaire();

        if ($questionnaire->getId() !== $questionnaireId) {
            throw $this->createNotFoundException('Question not found in this questionnaire');
        }

        if ($request->isMethod('POST')) {
            $this->handleQuestionForm($request, $question);

            $this->entityManager->flush();

            $this->addFlash('success', 'La question a été modifiée avec succès.');

            return $this->redirectToRoute('admin_question_index', ['questionnaireId' => $questionnaireId]);
        }

        return $this->render('admin/question/edit.html.twig', [
            'questionnaire' => $questionnaire,
            'question' => $question,
            'question_types' => Question::TYPES,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, int $questionnaireId, Question $question): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $question->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('admin_question_index', ['questionnaireId' => $questionnaireId]);
        }

        $this->entityManager->remove($question);
        $this->entityManager->flush();

        $this->addFlash('success', 'La question a été supprimée avec succès.');

        return $this->redirectToRoute('admin_question_index', ['questionnaireId' => $questionnaireId]);
    }

    #[Route('/{id}/duplicate', name: 'duplicate', methods: ['POST'])]
    public function duplicate(Request $request, int $questionnaireId, Question $question): Response
    {
        if (!$this->isCsrfTokenValid('duplicate' . $question->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');

            return $this->redirectToRoute('admin_question_index', ['questionnaireId' => $questionnaireId]);
        }

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

        $this->entityManager->persist($newQuestion);

        // Duplicate options
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
        }

        $this->entityManager->flush();

        $this->addFlash('success', 'La question a été dupliquée avec succès.');

        return $this->redirectToRoute('admin_question_index', ['questionnaireId' => $questionnaireId]);
    }

    #[Route('/reorder', name: 'reorder', methods: ['POST'])]
    public function reorder(Request $request, int $questionnaireId): JsonResponse
    {
        $questionIds = $request->request->get('questionIds', []);

        if (!is_array($questionIds)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid data']);
        }

        $questionnaire = $this->entityManager->getRepository(Questionnaire::class)->find($questionnaireId);

        if (!$questionnaire) {
            return new JsonResponse(['success' => false, 'message' => 'Questionnaire not found']);
        }

        try {
            $this->questionRepository->reorderQuestions($questionnaire, $questionIds);

            return new JsonResponse(['success' => true]);
        } catch (Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    #[Route('/{id}/toggle-status', name: 'toggle_status', methods: ['POST'])]
    public function toggleStatus(Request $request, int $questionnaireId, Question $question): JsonResponse
    {
        if (!$this->isCsrfTokenValid('toggle_status' . $question->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Token CSRF invalide']);
        }

        $question->setIsActive(!$question->isActive());
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'isActive' => $question->isActive(),
        ]);
    }

    private function handleQuestionForm(Request $request, Question $question): void
    {
        $data = $request->request->all();

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

        // Handle allowed file types
        if (!empty($data['allowedFileTypes'])) {
            $allowedTypes = array_map('trim', explode(',', $data['allowedFileTypes']));
            $question->setAllowedFileTypes($allowedTypes);
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

        // Handle options for choice questions
        if (in_array($question->getType(), [Question::TYPE_SINGLE_CHOICE, Question::TYPE_MULTIPLE_CHOICE], true)) {
            $this->handleQuestionOptions($data, $question);
        } else {
            // Remove existing options if question type changed
            foreach ($question->getOptions() as $option) {
                $this->entityManager->remove($option);
            }
        }
    }

    private function handleQuestionOptions(array $data, Question $question): void
    {
        // Get existing options
        $existingOptions = $question->getOptions()->toArray();
        $existingOptionIds = array_map(static fn ($option) => $option->getId(), $existingOptions);

        $submittedOptionIds = [];

        // Process submitted options
        if (isset($data['options']) && is_array($data['options'])) {
            foreach ($data['options'] as $index => $optionData) {
                $optionId = $optionData['id'] ?? null;
                $optionText = trim($optionData['text'] ?? '');

                if (empty($optionText)) {
                    continue;
                }

                if ($optionId && in_array($optionId, $existingOptionIds, true)) {
                    // Update existing option
                    $option = $this->entityManager->getRepository(QuestionOption::class)->find($optionId);
                    $submittedOptionIds[] = $optionId;
                } else {
                    // Create new option
                    $option = new QuestionOption();
                    $option->setQuestion($question);
                    $this->entityManager->persist($option);
                }

                $option->setOptionText($optionText)
                    ->setOrderIndex($index + 1)
                    ->setIsCorrect(isset($optionData['isCorrect']))
                    ->setIsActive($optionData['isActive'] ?? true)
                    ->setPoints(($optionData['points'] ?? null) ? (int) $optionData['points'] : 0)
                    ->setExplanation($optionData['explanation'] ?? null)
                ;
            }
        }

        // Remove options that were not submitted
        foreach ($existingOptions as $option) {
            if ($option->getId() && !in_array($option->getId(), $submittedOptionIds, true)) {
                $this->entityManager->remove($option);
            }
        }
    }
}
