<?php

declare(strict_types=1);

namespace App\Controller\Admin\Assessment;

use App\Entity\Assessment\Questionnaire;
use App\Entity\Assessment\QuestionnaireResponse;
use App\Repository\Assessment\QuestionnaireResponseRepository;
use App\Repository\Assessment\QuestionResponseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;
use ZipArchive;

/**
 * Admin controller for managing questionnaire responses and evaluation.
 */
#[Route('/admin/questionnaire-responses')]
class QuestionnaireResponseController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private QuestionnaireResponseRepository $responseRepository,
        private QuestionResponseRepository $questionResponseRepository,
        private LoggerInterface $logger,
    ) {}

    #[Route('', name: 'admin_questionnaire_response_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->logger->info('Starting questionnaire responses index view', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'request_params' => $request->query->all(),
        ]);

        try {
            $questionnaire = null;
            $questionnaireId = $request->query->get('questionnaire');
            $status = $request->query->get('status', '');
            $evaluationStatus = $request->query->get('evaluation_status', '');

            $this->logger->debug('Building questionnaire responses query with filters', [
                'questionnaire_id' => $questionnaireId,
                'status' => $status,
                'evaluation_status' => $evaluationStatus,
            ]);

            $queryBuilder = $this->responseRepository->createQueryBuilder('r')
                ->leftJoin('r.questionnaire', 'q')
                ->leftJoin('r.formation', 'f')
                ->addSelect('q', 'f')
            ;

            if ($questionnaireId) {
                $questionnaire = $this->entityManager->getRepository(Questionnaire::class)->find($questionnaireId);
                if ($questionnaire) {
                    $queryBuilder->andWhere('r.questionnaire = :questionnaire')
                        ->setParameter('questionnaire', $questionnaire)
                    ;
                    $this->logger->debug('Applied questionnaire filter', [
                        'questionnaire_id' => $questionnaire->getId(),
                        'questionnaire_title' => $questionnaire->getTitle(),
                    ]);
                }
            }

            if ($status) {
                $queryBuilder->andWhere('r.status = :status')
                    ->setParameter('status', $status)
                ;
                $this->logger->debug('Applied status filter', ['status' => $status]);
            }

            if ($evaluationStatus) {
                $queryBuilder->andWhere('r.evaluationStatus = :evaluationStatus')
                    ->setParameter('evaluationStatus', $evaluationStatus)
                ;
                $this->logger->debug('Applied evaluation status filter', ['evaluation_status' => $evaluationStatus]);
            }

            $responses = $queryBuilder->orderBy('r.createdAt', 'DESC')
                ->getQuery()
                ->getResult()
            ;

            $questionnaires = $this->entityManager->getRepository(Questionnaire::class)
                ->findBy(['status' => Questionnaire::STATUS_ACTIVE], ['title' => 'ASC'])
            ;

            $this->logger->info('Successfully retrieved questionnaire responses', [
                'responses_count' => count($responses),
                'questionnaires_count' => count($questionnaires),
                'filters_applied' => array_filter([
                    'questionnaire' => $questionnaireId,
                    'status' => $status,
                    'evaluation_status' => $evaluationStatus,
                ]),
            ]);

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
                    QuestionnaireResponse::STATUS_ABANDONED => 'Abandonné',
                ],
                'evaluation_statuses' => [
                    QuestionnaireResponse::EVALUATION_STATUS_PENDING => 'En attente',
                    QuestionnaireResponse::EVALUATION_STATUS_IN_REVIEW => 'En cours d\'évaluation',
                    QuestionnaireResponse::EVALUATION_STATUS_COMPLETED => 'Évalué',
                ],
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Error in questionnaire responses index view', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'request_params' => $request->query->all(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des réponses aux questionnaires.');

            return $this->render('admin/questionnaire_response/index.html.twig', [
                'responses' => [],
                'questionnaires' => [],
                'current_questionnaire' => null,
                'current_status' => '',
                'current_evaluation_status' => '',
                'statuses' => [
                    QuestionnaireResponse::STATUS_STARTED => 'Démarré',
                    QuestionnaireResponse::STATUS_IN_PROGRESS => 'En cours',
                    QuestionnaireResponse::STATUS_COMPLETED => 'Terminé',
                    QuestionnaireResponse::STATUS_ABANDONED => 'Abandonné',
                ],
                'evaluation_statuses' => [
                    QuestionnaireResponse::EVALUATION_STATUS_PENDING => 'En attente',
                    QuestionnaireResponse::EVALUATION_STATUS_IN_REVIEW => 'En cours d\'évaluation',
                    QuestionnaireResponse::EVALUATION_STATUS_COMPLETED => 'Évalué',
                ],
            ]);
        }
    }

    #[Route('/pending-evaluation', name: 'admin_questionnaire_response_pending_evaluation', methods: ['GET'])]
    public function pendingEvaluation(): Response
    {
        $responses = $this->responseRepository->findPendingEvaluation();

        return $this->render('admin/questionnaire_response/pending_evaluation.html.twig', [
            'responses' => $responses,
        ]);
    }

    #[Route('/{id}', name: 'admin_questionnaire_response_show', methods: ['GET'])]
    public function show(QuestionnaireResponse $response): Response
    {
        $questionResponses = $this->questionResponseRepository->findByQuestionnaireResponse($response);

        return $this->render('admin/questionnaire_response/show.html.twig', [
            'response' => $response,
            'question_responses' => $questionResponses,
        ]);
    }

    #[Route('/{id}/evaluate', name: 'admin_questionnaire_response_evaluate', methods: ['GET', 'POST'])]
    public function evaluate(Request $request, QuestionnaireResponse $response): Response
    {
        $this->logger->info('Starting questionnaire response evaluation', [
            'response_id' => $response->getId(),
            'questionnaire_id' => $response->getQuestionnaire()->getId(),
            'questionnaire_title' => $response->getQuestionnaire()->getTitle(),
            'respondent_email' => $response->getEmail(),
            'current_status' => $response->getStatus(),
            'current_evaluation_status' => $response->getEvaluationStatus(),
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'method' => $request->getMethod(),
        ]);

        try {
            if (!$response->isCompleted()) {
                $this->logger->warning('Attempted to evaluate incomplete response', [
                    'response_id' => $response->getId(),
                    'current_status' => $response->getStatus(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('error', 'Seules les réponses terminées peuvent être évaluées.');

                return $this->redirectToRoute('admin_questionnaire_response_show', ['id' => $response->getId()]);
            }

            if ($request->isMethod('POST')) {
                $data = $request->request->all();

                $this->logger->debug('Processing evaluation form', [
                    'response_id' => $response->getId(),
                    'evaluation_status' => $data['evaluation_status'] ?? null,
                    'question_scores_count' => isset($data['question_scores']) ? count($data['question_scores']) : 0,
                    'has_notes' => !empty($data['evaluator_notes']),
                    'has_recommendation' => !empty($data['recommendation']),
                ]);

                $response->setEvaluationStatus($data['evaluation_status'] ?? QuestionnaireResponse::EVALUATION_STATUS_COMPLETED)
                    ->setEvaluatorNotes($data['evaluator_notes'] ?? null)
                    ->setRecommendation($data['recommendation'] ?? null)
                ;

                // Update individual question scores if provided
                $updatedScores = 0;
                if (isset($data['question_scores']) && is_array($data['question_scores'])) {
                    foreach ($data['question_scores'] as $questionResponseId => $score) {
                        $questionResponse = $this->questionResponseRepository->find($questionResponseId);
                        if ($questionResponse && $questionResponse->getQuestionnaireResponse() === $response) {
                            $questionResponse->setScoreEarned((int) $score);
                            $updatedScores++;

                            $this->logger->debug('Updated question score', [
                                'question_response_id' => $questionResponseId,
                                'new_score' => $score,
                                'response_id' => $response->getId(),
                            ]);
                        }
                    }
                }

                $response->markAsEvaluated();
                $response->calculateScore();

                $this->entityManager->flush();

                $this->logger->info('Questionnaire response evaluation completed successfully', [
                    'response_id' => $response->getId(),
                    'evaluation_status' => $response->getEvaluationStatus(),
                    'total_score' => $response->getTotalScore(),
                    'score_percentage' => $response->getScorePercentage(),
                    'updated_scores_count' => $updatedScores,
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('success', 'L\'évaluation a été enregistrée avec succès.');

                return $this->redirectToRoute('admin_questionnaire_response_show', ['id' => $response->getId()]);
            }

            $questionResponses = $this->questionResponseRepository->findByQuestionnaireResponse($response);

            $this->logger->debug('Loaded question responses for evaluation', [
                'response_id' => $response->getId(),
                'question_responses_count' => count($questionResponses),
            ]);

            return $this->render('admin/questionnaire_response/evaluate.html.twig', [
                'response' => $response,
                'question_responses' => $questionResponses,
                'evaluation_statuses' => [
                    QuestionnaireResponse::EVALUATION_STATUS_PENDING => 'En attente',
                    QuestionnaireResponse::EVALUATION_STATUS_IN_REVIEW => 'En cours d\'évaluation',
                    QuestionnaireResponse::EVALUATION_STATUS_COMPLETED => 'Évalué',
                ],
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Error in questionnaire response evaluation', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'response_id' => $response->getId(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'method' => $request->getMethod(),
                'form_data' => $request->isMethod('POST') ? $request->request->all() : null,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'évaluation.');

            if ($request->isMethod('POST')) {
                return $this->redirectToRoute('admin_questionnaire_response_show', ['id' => $response->getId()]);
            }

            return $this->render('admin/questionnaire_response/evaluate.html.twig', [
                'response' => $response,
                'question_responses' => [],
                'evaluation_statuses' => [
                    QuestionnaireResponse::EVALUATION_STATUS_PENDING => 'En attente',
                    QuestionnaireResponse::EVALUATION_STATUS_IN_REVIEW => 'En cours d\'évaluation',
                    QuestionnaireResponse::EVALUATION_STATUS_COMPLETED => 'Évalué',
                ],
            ]);
        }
    }

    #[Route('/{id}/start-evaluation', name: 'admin_questionnaire_response_start_evaluation', methods: ['POST'])]
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

    #[Route('/{id}/download-files', name: 'admin_questionnaire_response_download_files', methods: ['GET'])]
    public function downloadFiles(QuestionnaireResponse $response): Response
    {
        $this->logger->info('Starting file download for questionnaire response', [
            'response_id' => $response->getId(),
            'questionnaire_id' => $response->getQuestionnaire()->getId(),
            'respondent_email' => $response->getEmail(),
            'user_id' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            $questionResponses = $this->questionResponseRepository->findByQuestionnaireResponse($response);
            $files = [];

            foreach ($questionResponses as $questionResponse) {
                if ($questionResponse->getFileResponse()) {
                    $filePath = $this->getParameter('kernel.project_dir') . '/public/uploads/questionnaire_files/' . $questionResponse->getFileResponse();
                    if (file_exists($filePath)) {
                        $files[] = [
                            'path' => $filePath,
                            'name' => $questionResponse->getQuestion()->getQuestionText() . '_' . $questionResponse->getFileResponse(),
                            'question' => $questionResponse->getQuestion()->getQuestionText(),
                        ];

                        $this->logger->debug('Found file for download', [
                            'file_path' => $filePath,
                            'question_id' => $questionResponse->getQuestion()->getId(),
                            'response_id' => $response->getId(),
                        ]);
                    } else {
                        $this->logger->warning('File not found on disk', [
                            'expected_path' => $filePath,
                            'question_response_id' => $questionResponse->getId(),
                            'response_id' => $response->getId(),
                        ]);
                    }
                }
            }

            if (empty($files)) {
                $this->logger->info('No files found for download', [
                    'response_id' => $response->getId(),
                    'question_responses_count' => count($questionResponses),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('error', 'Aucun fichier trouvé pour cette réponse.');

                return $this->redirectToRoute('admin_questionnaire_response_show', ['id' => $response->getId()]);
            }

            // If only one file, download it directly
            if (count($files) === 1) {
                $file = $files[0];

                $this->logger->info('Downloading single file', [
                    'file_path' => $file['path'],
                    'file_name' => $file['name'],
                    'response_id' => $response->getId(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);

                $binaryResponse = new BinaryFileResponse($file['path']);
                $binaryResponse->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file['name']);

                return $binaryResponse;
            }

            // Multiple files - create a ZIP
            $this->logger->debug('Creating ZIP archive for multiple files', [
                'files_count' => count($files),
                'response_id' => $response->getId(),
            ]);

            $zipPath = $this->createZipFromFiles($files, $response);

            if (!$zipPath) {
                $this->logger->error('Failed to create ZIP archive', [
                    'files_count' => count($files),
                    'response_id' => $response->getId(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('error', 'Erreur lors de la création de l\'archive.');

                return $this->redirectToRoute('admin_questionnaire_response_show', ['id' => $response->getId()]);
            }

            $this->logger->info('Successfully created and downloading ZIP archive', [
                'zip_path' => $zipPath,
                'files_count' => count($files),
                'response_id' => $response->getId(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            $binaryResponse = new BinaryFileResponse($zipPath);
            $binaryResponse->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'questionnaire_' . $response->getId() . '_files.zip',
            );

            // Delete the temporary ZIP file after sending
            $binaryResponse->deleteFileAfterSend(true);

            return $binaryResponse;
        } catch (Throwable $e) {
            $this->logger->error('Error downloading questionnaire response files', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'response_id' => $response->getId(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du téléchargement des fichiers.');

            return $this->redirectToRoute('admin_questionnaire_response_show', ['id' => $response->getId()]);
        }
    }

    #[Route('/statistics/{id}', name: 'admin_questionnaire_response_statistics', methods: ['GET'])]
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
                'statistics' => $this->questionResponseRepository->getQuestionStatistics($question),
            ];
        }

        return $this->render('admin/questionnaire_response/statistics.html.twig', [
            'questionnaire' => $questionnaire,
            'completion_stats' => $completionStats,
            'evaluation_stats' => $evaluationStats,
            'average_time' => $averageTime,
            'score_distribution' => $scoreDistribution,
            'question_stats' => $questionStats,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_questionnaire_response_delete', methods: ['POST'])]
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
        $this->logger->debug('Starting ZIP file creation', [
            'files_count' => count($files),
            'response_id' => $response->getId(),
        ]);

        try {
            $zip = new ZipArchive();
            $zipPath = sys_get_temp_dir() . '/questionnaire_' . $response->getId() . '_' . time() . '.zip';

            if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
                $this->logger->error('Failed to create ZIP archive', [
                    'zip_path' => $zipPath,
                    'response_id' => $response->getId(),
                ]);

                return null;
            }

            $addedFiles = 0;
            foreach ($files as $file) {
                if ($zip->addFile($file['path'], $file['name'])) {
                    $addedFiles++;
                    $this->logger->debug('Added file to ZIP', [
                        'file_path' => $file['path'],
                        'zip_name' => $file['name'],
                    ]);
                } else {
                    $this->logger->warning('Failed to add file to ZIP', [
                        'file_path' => $file['path'],
                        'zip_name' => $file['name'],
                    ]);
                }
            }

            $zip->close();

            $this->logger->info('ZIP file created successfully', [
                'zip_path' => $zipPath,
                'files_requested' => count($files),
                'files_added' => $addedFiles,
                'response_id' => $response->getId(),
            ]);

            return $zipPath;
        } catch (Throwable $e) {
            $this->logger->error('Error creating ZIP file', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'response_id' => $response->getId(),
                'files_count' => count($files),
            ]);

            return null;
        }
    }
}
