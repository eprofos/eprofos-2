<?php

namespace App\Controller\Admin;

use App\Entity\Assessment\QuestionnaireResponse;
use App\Entity\Assessment\Questionnaire;
use App\Repository\QuestionnaireResponseRepository;
use App\Repository\QuestionResponseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Admin controller for managing questionnaire responses and evaluation
 */
#[Route('/admin/questionnaire-responses', name: 'admin_questionnaire_response_')]
class QuestionnaireResponseController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private QuestionnaireResponseRepository $responseRepository,
        private QuestionResponseRepository $questionResponseRepository
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $questionnaire = null;
        $questionnaireId = $request->query->get('questionnaire');
        $status = $request->query->get('status', '');
        $evaluationStatus = $request->query->get('evaluation_status', '');

        $queryBuilder = $this->responseRepository->createQueryBuilder('r')
            ->leftJoin('r.questionnaire', 'q')
            ->leftJoin('r.formation', 'f')
            ->addSelect('q', 'f');

        if ($questionnaireId) {
            $questionnaire = $this->entityManager->getRepository(Questionnaire::class)->find($questionnaireId);
            if ($questionnaire) {
                $queryBuilder->andWhere('r.questionnaire = :questionnaire')
                    ->setParameter('questionnaire', $questionnaire);
            }
        }

        if ($status) {
            $queryBuilder->andWhere('r.status = :status')
                ->setParameter('status', $status);
        }

        if ($evaluationStatus) {
            $queryBuilder->andWhere('r.evaluationStatus = :evaluationStatus')
                ->setParameter('evaluationStatus', $evaluationStatus);
        }

        $responses = $queryBuilder->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $questionnaires = $this->entityManager->getRepository(Questionnaire::class)
            ->findBy(['status' => Questionnaire::STATUS_ACTIVE], ['title' => 'ASC']);

        return $this->render('admin/questionnaire_response/index.html.twig', [
            'responses' => $responses,
            'questionnaires' => $questionnaires,
            'current_questionnaire' => $questionnaire,
            'current_status' => $status,
            'current_evaluation_status' => $evaluationStatus,
            'statuses' => [
                QuestionnaireResponse::STATUS_STARTED => 'Démarré',
                QuestionnaireResponse::STATUS_IN_PROGRESS => 'En cours',
                QuestionnaireResponse::STATUS_COMPLETED => 'Terminé',
                QuestionnaireResponse::STATUS_ABANDONED => 'Abandonné'
            ],
            'evaluation_statuses' => [
                QuestionnaireResponse::EVALUATION_STATUS_PENDING => 'En attente',
                QuestionnaireResponse::EVALUATION_STATUS_IN_REVIEW => 'En cours d\'évaluation',
                QuestionnaireResponse::EVALUATION_STATUS_COMPLETED => 'Évalué'
            ]
        ]);
    }

    #[Route('/pending-evaluation', name: 'pending_evaluation', methods: ['GET'])]
    public function pendingEvaluation(): Response
    {
        $responses = $this->responseRepository->findPendingEvaluation();

        return $this->render('admin/questionnaire_response/pending_evaluation.html.twig', [
            'responses' => $responses
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(QuestionnaireResponse $response): Response
    {
        $questionResponses = $this->questionResponseRepository->findByQuestionnaireResponse($response);

        return $this->render('admin/questionnaire_response/show.html.twig', [
            'response' => $response,
            'question_responses' => $questionResponses
        ]);
    }

    #[Route('/{id}/evaluate', name: 'evaluate', methods: ['GET', 'POST'])]
    public function evaluate(Request $request, QuestionnaireResponse $response): Response
    {
        if (!$response->isCompleted()) {
            $this->addFlash('error', 'Seules les réponses terminées peuvent être évaluées.');
            return $this->redirectToRoute('admin_questionnaire_response_show', ['id' => $response->getId()]);
        }

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $response->setEvaluationStatus($data['evaluation_status'] ?? QuestionnaireResponse::EVALUATION_STATUS_COMPLETED)
                ->setEvaluatorNotes($data['evaluator_notes'] ?? null)
                ->setRecommendation($data['recommendation'] ?? null);

            // Update individual question scores if provided
            if (isset($data['question_scores']) && is_array($data['question_scores'])) {
                foreach ($data['question_scores'] as $questionResponseId => $score) {
                    $questionResponse = $this->questionResponseRepository->find($questionResponseId);
                    if ($questionResponse && $questionResponse->getQuestionnaireResponse() === $response) {
                        $questionResponse->setScoreEarned((int) $score);
                    }
                }
            }

            $response->markAsEvaluated();
            $response->calculateScore();

            $this->entityManager->flush();

            $this->addFlash('success', 'L\'évaluation a été enregistrée avec succès.');
            return $this->redirectToRoute('admin_questionnaire_response_show', ['id' => $response->getId()]);
        }

        $questionResponses = $this->questionResponseRepository->findByQuestionnaireResponse($response);

        return $this->render('admin/questionnaire_response/evaluate.html.twig', [
            'response' => $response,
            'question_responses' => $questionResponses,
            'evaluation_statuses' => [
                QuestionnaireResponse::EVALUATION_STATUS_PENDING => 'En attente',
                QuestionnaireResponse::EVALUATION_STATUS_IN_REVIEW => 'En cours d\'évaluation',
                QuestionnaireResponse::EVALUATION_STATUS_COMPLETED => 'Évalué'
            ]
        ]);
    }

    #[Route('/{id}/start-evaluation', name: 'start_evaluation', methods: ['POST'])]
    public function startEvaluation(Request $request, QuestionnaireResponse $response): JsonResponse
    {
        if (!$this->isCsrfTokenValid('start_evaluation' . $response->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['success' => false, 'message' => 'Token CSRF invalide']);
        }

        if (!$response->isCompleted()) {
            return new JsonResponse(['success' => false, 'message' => 'Seules les réponses terminées peuvent être évaluées']);
        }

        $response->setEvaluationStatus(QuestionnaireResponse::EVALUATION_STATUS_IN_REVIEW);
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/{id}/download-files', name: 'download_files', methods: ['GET'])]
    public function downloadFiles(QuestionnaireResponse $response): Response
    {
        $questionResponses = $this->questionResponseRepository->findByQuestionnaireResponse($response);
        $files = [];

        foreach ($questionResponses as $questionResponse) {
            if ($questionResponse->getFileResponse()) {
                $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/questionnaire_files/' . $questionResponse->getFileResponse();
                if (file_exists($filePath)) {
                    $files[] = [
                        'path' => $filePath,
                        'name' => $questionResponse->getQuestion()->getQuestionText() . '_' . $questionResponse->getFileResponse(),
                        'question' => $questionResponse->getQuestion()->getQuestionText()
                    ];
                }
            }
        }

        if (empty($files)) {
            $this->addFlash('error', 'Aucun fichier trouvé pour cette réponse.');
            return $this->redirectToRoute('admin_questionnaire_response_show', ['id' => $response->getId()]);
        }

        // If only one file, download it directly
        if (count($files) === 1) {
            $file = $files[0];
            $binaryResponse = new BinaryFileResponse($file['path']);
            $binaryResponse->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file['name']);
            return $binaryResponse;
        }

        // Multiple files - create a ZIP
        $zipPath = $this->createZipFromFiles($files, $response);
        
        if (!$zipPath) {
            $this->addFlash('error', 'Erreur lors de la création de l\'archive.');
            return $this->redirectToRoute('admin_questionnaire_response_show', ['id' => $response->getId()]);
        }

        $binaryResponse = new BinaryFileResponse($zipPath);
        $binaryResponse->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'questionnaire_' . $response->getId() . '_files.zip'
        );

        // Delete the temporary ZIP file after sending
        $binaryResponse->deleteFileAfterSend(true);

        return $binaryResponse;
    }

    #[Route('/statistics/{id}', name: 'statistics', methods: ['GET'])]
    public function statistics(Questionnaire $questionnaire): Response
    {
        $completionStats = $this->responseRepository->getCompletionStatistics($questionnaire);
        $evaluationStats = $this->responseRepository->getEvaluationStatistics($questionnaire);
        $averageTime = $this->responseRepository->getAverageCompletionTime($questionnaire);
        $scoreDistribution = $this->responseRepository->getScoreDistribution($questionnaire);

        // Get question statistics
        $questionStats = [];
        foreach ($questionnaire->getActiveQuestions() as $question) {
            $questionStats[] = [
                'question' => $question,
                'statistics' => $this->questionResponseRepository->getQuestionStatistics($question)
            ];
        }

        return $this->render('admin/questionnaire_response/statistics.html.twig', [
            'questionnaire' => $questionnaire,
            'completion_stats' => $completionStats,
            'evaluation_stats' => $evaluationStats,
            'average_time' => $averageTime,
            'score_distribution' => $scoreDistribution,
            'question_stats' => $questionStats
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, QuestionnaireResponse $response): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $response->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_questionnaire_response_index');
        }

        $this->entityManager->remove($response);
        $this->entityManager->flush();

        $this->addFlash('success', 'La réponse a été supprimée avec succès.');
        return $this->redirectToRoute('admin_questionnaire_response_index');
    }

    private function createZipFromFiles(array $files, QuestionnaireResponse $response): ?string
    {
        $zip = new \ZipArchive();
        $zipPath = sys_get_temp_dir() . '/questionnaire_' . $response->getId() . '_' . time() . '.zip';

        if ($zip->open($zipPath, \ZipArchive::CREATE) !== TRUE) {
            return null;
        }

        foreach ($files as $file) {
            $zip->addFile($file['path'], $file['name']);
        }

        $zip->close();

        return $zipPath;
    }
}
