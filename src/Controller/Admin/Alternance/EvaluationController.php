<?php

declare(strict_types=1);

namespace App\Controller\Admin\Alternance;

use App\Entity\Alternance\ProgressAssessment;
use App\Entity\Alternance\SkillsAssessment;
use App\Repository\Alternance\AlternanceContractRepository;
use App\Repository\Alternance\ProgressAssessmentRepository;
use App\Repository\Alternance\SkillsAssessmentRepository;
use App\Service\Alternance\ProgressAssessmentService;
use App\Service\Alternance\SkillsAssessmentService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

#[Route('/admin/alternance/evaluations')]
#[IsGranted('ROLE_ADMIN')]
class EvaluationController extends AbstractController
{
    public function __construct(
        private ProgressAssessmentRepository $progressRepository,
        private SkillsAssessmentRepository $skillsRepository,
        private AlternanceContractRepository $contractRepository,
        private ProgressAssessmentService $progressService,
        private SkillsAssessmentService $skillsService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Route('', name: 'admin_alternance_evaluation_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $startTime = microtime(true);
        $user = $this->getUser();
        $userIdentifier = $user?->getUserIdentifier();

        try {
            $page = $request->query->getInt('page', 1);
            $search = $request->query->get('search', '');
            $type = $request->query->get('type', '');
            $status = $request->query->get('status', '');
            $perPage = 20;

            $this->logger->info('Admin alternance evaluation index page accessed', [
                'user_id' => $userIdentifier,
                'page' => $page,
                'search' => $search,
                'type' => $type,
                'status' => $status,
                'per_page' => $perPage,
                'ip_address' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
            ]);

            $filters = [
                'search' => $search,
                'type' => $type,
                'status' => $status,
            ];

            // Get both types of evaluations with detailed logging
            $this->logger->debug('Fetching progress evaluations from repository', [
                'user_id' => $userIdentifier,
                'filters' => $filters,
                'page' => $page,
                'per_page' => $perPage,
            ]);

            $progressEvaluations = $this->progressRepository->findPaginatedAssessments($filters, $page, $perPage);

            $this->logger->debug('Progress evaluations retrieved successfully', [
                'user_id' => $userIdentifier,
                'count' => count($progressEvaluations),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            $this->logger->debug('Fetching skills evaluations from repository', [
                'user_id' => $userIdentifier,
                'filters' => $filters,
                'page' => $page,
                'per_page' => $perPage,
            ]);

            $skillsEvaluations = $this->skillsRepository->findPaginatedAssessments($filters, $page, $perPage);

            $this->logger->debug('Skills evaluations retrieved successfully', [
                'user_id' => $userIdentifier,
                'count' => count($skillsEvaluations),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            // Combine and sort by date
            $evaluations = array_merge($progressEvaluations, $skillsEvaluations);
            $this->logger->debug('Combined evaluations arrays', [
                'user_id' => $userIdentifier,
                'total_count' => count($evaluations),
                'progress_count' => count($progressEvaluations),
                'skills_count' => count($skillsEvaluations),
            ]);

            usort($evaluations, static fn ($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());

            // Get evaluation statistics with error handling
            $this->logger->debug('Fetching evaluation statistics', [
                'user_id' => $userIdentifier,
            ]);

            $statistics = $this->getEvaluationStatistics();

            $this->logger->debug('Evaluation statistics retrieved successfully', [
                'user_id' => $userIdentifier,
                'statistics_keys' => array_keys($statistics),
                'total_evaluations' => $statistics['total_evaluations'] ?? 0,
                'pending_validation' => $statistics['pending_validation'] ?? 0,
            ]);

            $totalPages = ceil(count($evaluations) / $perPage);

            $this->logger->info('Admin alternance evaluation index page rendered successfully', [
                'user_id' => $userIdentifier,
                'total_evaluations' => count($evaluations),
                'current_page' => $page,
                'total_pages' => $totalPages,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            return $this->render('admin/alternance/evaluation/index.html.twig', [
                'evaluations' => $evaluations,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'filters' => $filters,
                'statistics' => $statistics,
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Error in admin alternance evaluation index', [
                'user_id' => $userIdentifier,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'request_parameters' => $request->query->all(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des évaluations. Veuillez réessayer.');

            // Return a minimal error page with empty data
            return $this->render('admin/alternance/evaluation/index.html.twig', [
                'evaluations' => [],
                'current_page' => 1,
                'total_pages' => 0,
                'filters' => [],
                'statistics' => [],
            ]);
        }
    }

    #[Route('/progress', name: 'admin_alternance_evaluation_progress', methods: ['GET'])]
    public function progressEvaluations(Request $request): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();

        try {
            $page = $request->query->getInt('page', 1);
            $search = $request->query->get('search', '');
            $status = $request->query->get('status', '');
            $perPage = 20;

            $this->logger->info('Admin progress evaluations page accessed', [
                'user_id' => $userIdentifier,
                'page' => $page,
                'search' => $search,
                'status' => $status,
                'per_page' => $perPage,
                'ip_address' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
            ]);

            $filters = [
                'search' => $search,
                'status' => $status,
            ];

            $this->logger->debug('Fetching progress evaluations with filters', [
                'user_id' => $userIdentifier,
                'filters' => $filters,
                'page' => $page,
                'per_page' => $perPage,
            ]);

            $evaluations = $this->progressRepository->findPaginatedAssessments($filters, $page, $perPage);
            $totalCount = $this->progressRepository->countFilteredAssessments($filters);
            $totalPages = ceil($totalCount / $perPage);

            $this->logger->debug('Progress evaluations retrieved successfully', [
                'user_id' => $userIdentifier,
                'count' => count($evaluations),
                'total_count' => $totalCount,
                'total_pages' => $totalPages,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            $this->logger->info('Admin progress evaluations page rendered successfully', [
                'user_id' => $userIdentifier,
                'total_evaluations' => count($evaluations),
                'current_page' => $page,
                'total_pages' => $totalPages,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            return $this->render('admin/alternance/evaluation/progress.html.twig', [
                'evaluations' => $evaluations,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'filters' => $filters,
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Error in admin progress evaluations', [
                'user_id' => $userIdentifier,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'request_parameters' => $request->query->all(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des évaluations de progression. Veuillez réessayer.');

            // Return a minimal error page with empty data
            return $this->render('admin/alternance/evaluation/progress.html.twig', [
                'evaluations' => [],
                'current_page' => 1,
                'total_pages' => 0,
                'filters' => [],
            ]);
        }
    }

    #[Route('/skills', name: 'admin_alternance_evaluation_skills', methods: ['GET'])]
    public function skillsEvaluations(Request $request): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();

        try {
            $page = $request->query->getInt('page', 1);
            $search = $request->query->get('search', '');
            $status = $request->query->get('status', '');
            $perPage = 20;

            $this->logger->info('Admin skills evaluations page accessed', [
                'user_id' => $userIdentifier,
                'page' => $page,
                'search' => $search,
                'status' => $status,
                'per_page' => $perPage,
                'ip_address' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
            ]);

            $filters = [
                'search' => $search,
                'status' => $status,
            ];

            $this->logger->debug('Fetching skills evaluations with filters', [
                'user_id' => $userIdentifier,
                'filters' => $filters,
                'page' => $page,
                'per_page' => $perPage,
            ]);

            $evaluations = $this->skillsRepository->findPaginatedAssessments($filters, $page, $perPage);
            $totalCount = $this->skillsRepository->countFilteredAssessments($filters);
            $totalPages = ceil($totalCount / $perPage);

            $this->logger->debug('Skills evaluations retrieved successfully', [
                'user_id' => $userIdentifier,
                'count' => count($evaluations),
                'total_count' => $totalCount,
                'total_pages' => $totalPages,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            $this->logger->info('Admin skills evaluations page rendered successfully', [
                'user_id' => $userIdentifier,
                'total_evaluations' => count($evaluations),
                'current_page' => $page,
                'total_pages' => $totalPages,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            return $this->render('admin/alternance/evaluation/skills.html.twig', [
                'evaluations' => $evaluations,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'filters' => $filters,
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Error in admin skills evaluations', [
                'user_id' => $userIdentifier,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'request_parameters' => $request->query->all(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des évaluations de compétences. Veuillez réessayer.');

            // Return a minimal error page with empty data
            return $this->render('admin/alternance/evaluation/skills.html.twig', [
                'evaluations' => [],
                'current_page' => 1,
                'total_pages' => 0,
                'filters' => [],
            ]);
        }
    }

    #[Route('/progress/{id}', name: 'admin_alternance_evaluation_progress_show', methods: ['GET'])]
    public function showProgressEvaluation(ProgressAssessment $evaluation): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();

        try {
            $this->logger->info('Admin progress evaluation detail page accessed', [
                'user_id' => $userIdentifier,
                'evaluation_id' => $evaluation->getId(),
                'student_name' => $evaluation->getStudent()?->getFullName(),
                'mentor_name' => $evaluation->getMentor()?->getFullName(),
                'evaluation_status' => $evaluation->getStatus(),
                'evaluation_score' => $evaluation->getOverallScore(),
            ]);

            $this->logger->debug('Analyzing progress assessment', [
                'user_id' => $userIdentifier,
                'evaluation_id' => $evaluation->getId(),
            ]);

            $analysis = $this->progressService->analyzeAssessment($evaluation);

            $this->logger->debug('Progress assessment analysis completed', [
                'user_id' => $userIdentifier,
                'evaluation_id' => $evaluation->getId(),
                'analysis_keys' => array_keys($analysis),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            $this->logger->info('Admin progress evaluation detail page rendered successfully', [
                'user_id' => $userIdentifier,
                'evaluation_id' => $evaluation->getId(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            return $this->render('admin/alternance/evaluation/progress_show.html.twig', [
                'evaluation' => $evaluation,
                'analysis' => $analysis,
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Error in admin progress evaluation detail', [
                'user_id' => $userIdentifier,
                'evaluation_id' => $evaluation->getId(),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement de l\'évaluation de progression. Veuillez réessayer.');

            return $this->redirectToRoute('admin_alternance_evaluation_progress');
        }
    }

    #[Route('/skills/{id}', name: 'admin_alternance_evaluation_skills_show', methods: ['GET'])]
    public function showSkillsEvaluation(SkillsAssessment $evaluation): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();

        try {
            $this->logger->info('Admin skills evaluation detail page accessed', [
                'user_id' => $userIdentifier,
                'evaluation_id' => $evaluation->getId(),
                'student_name' => $evaluation->getStudent()?->getFullName(),
                'mentor_name' => $evaluation->getMentor()?->getFullName(),
                'evaluation_status' => $evaluation->getStatus(),
                'evaluation_score' => $evaluation->getOverallScore(),
            ]);

            $this->logger->debug('Analyzing skills assessment', [
                'user_id' => $userIdentifier,
                'evaluation_id' => $evaluation->getId(),
            ]);

            $analysis = $this->skillsService->analyzeSkillsAssessment($evaluation);

            $this->logger->debug('Skills assessment analysis completed', [
                'user_id' => $userIdentifier,
                'evaluation_id' => $evaluation->getId(),
                'analysis_keys' => array_keys($analysis),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            $this->logger->info('Admin skills evaluation detail page rendered successfully', [
                'user_id' => $userIdentifier,
                'evaluation_id' => $evaluation->getId(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            return $this->render('admin/alternance/evaluation/skills_show.html.twig', [
                'evaluation' => $evaluation,
                'analysis' => $analysis,
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Error in admin skills evaluation detail', [
                'user_id' => $userIdentifier,
                'evaluation_id' => $evaluation->getId(),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement de l\'évaluation de compétences. Veuillez réessayer.');

            return $this->redirectToRoute('admin_alternance_evaluation_skills');
        }
    }

    #[Route('/progress/{id}/validate', name: 'admin_alternance_evaluation_progress_validate', methods: ['POST'])]
    public function validateProgressEvaluation(Request $request, ProgressAssessment $evaluation): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();

        try {
            $action = $request->request->get('action'); // 'approve' or 'reject'
            $comments = $request->request->get('comments', '');

            $this->logger->info('Admin progress evaluation validation requested', [
                'user_id' => $userIdentifier,
                'evaluation_id' => $evaluation->getId(),
                'action' => $action,
                'has_comments' => !empty($comments),
                'comments_length' => strlen($comments),
                'student_name' => $evaluation->getStudent()?->getFullName(),
                'mentor_name' => $evaluation->getMentor()?->getFullName(),
                'current_status' => $evaluation->getStatus(),
                'ip_address' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
            ]);

            if (!in_array($action, ['approve', 'reject'], true)) {
                $this->logger->warning('Invalid validation action requested', [
                    'user_id' => $userIdentifier,
                    'evaluation_id' => $evaluation->getId(),
                    'invalid_action' => $action,
                    'valid_actions' => ['approve', 'reject'],
                ]);

                $this->addFlash('error', 'Action invalide.');

                return $this->redirectToRoute('admin_alternance_evaluation_progress_show', ['id' => $evaluation->getId()]);
            }

            if ($action === 'approve') {
                $this->logger->debug('Processing progress evaluation approval', [
                    'user_id' => $userIdentifier,
                    'evaluation_id' => $evaluation->getId(),
                    'comments' => $comments,
                ]);

                $this->progressService->approveAssessment($evaluation, $comments);

                $this->logger->info('Progress evaluation approved successfully', [
                    'user_id' => $userIdentifier,
                    'evaluation_id' => $evaluation->getId(),
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                $this->addFlash('success', 'Évaluation approuvée avec succès.');
            } elseif ($action === 'reject') {
                $this->logger->debug('Processing progress evaluation rejection', [
                    'user_id' => $userIdentifier,
                    'evaluation_id' => $evaluation->getId(),
                    'comments' => $comments,
                ]);

                $this->progressService->rejectAssessment($evaluation, $comments);

                $this->logger->info('Progress evaluation rejected successfully', [
                    'user_id' => $userIdentifier,
                    'evaluation_id' => $evaluation->getId(),
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                $this->addFlash('success', 'Évaluation rejetée avec succès.');
            }
        } catch (Exception $e) {
            $this->logger->error('Error in progress evaluation validation', [
                'user_id' => $userIdentifier,
                'evaluation_id' => $evaluation->getId(),
                'action' => $action ?? 'unknown',
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            $this->addFlash('error', 'Erreur lors de la validation : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_alternance_evaluation_progress_show', ['id' => $evaluation->getId()]);
    }

    #[Route('/skills/{id}/validate', name: 'admin_alternance_evaluation_skills_validate', methods: ['POST'])]
    public function validateSkillsEvaluation(Request $request, SkillsAssessment $evaluation): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();

        try {
            $action = $request->request->get('action'); // 'approve' or 'reject'
            $comments = $request->request->get('comments', '');

            $this->logger->info('Admin skills evaluation validation requested', [
                'user_id' => $userIdentifier,
                'evaluation_id' => $evaluation->getId(),
                'action' => $action,
                'has_comments' => !empty($comments),
                'comments_length' => strlen($comments),
                'student_name' => $evaluation->getStudent()?->getFullName(),
                'mentor_name' => $evaluation->getMentor()?->getFullName(),
                'current_status' => $evaluation->getStatus(),
                'ip_address' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
            ]);

            if (!in_array($action, ['approve', 'reject'], true)) {
                $this->logger->warning('Invalid validation action requested', [
                    'user_id' => $userIdentifier,
                    'evaluation_id' => $evaluation->getId(),
                    'invalid_action' => $action,
                    'valid_actions' => ['approve', 'reject'],
                ]);

                $this->addFlash('error', 'Action invalide.');

                return $this->redirectToRoute('admin_alternance_evaluation_skills_show', ['id' => $evaluation->getId()]);
            }

            if ($action === 'approve') {
                $this->logger->debug('Processing skills evaluation approval', [
                    'user_id' => $userIdentifier,
                    'evaluation_id' => $evaluation->getId(),
                    'comments' => $comments,
                ]);

                $this->skillsService->approveAssessment($evaluation, $comments);

                $this->logger->info('Skills evaluation approved successfully', [
                    'user_id' => $userIdentifier,
                    'evaluation_id' => $evaluation->getId(),
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                $this->addFlash('success', 'Évaluation approuvée avec succès.');
            } elseif ($action === 'reject') {
                $this->logger->debug('Processing skills evaluation rejection', [
                    'user_id' => $userIdentifier,
                    'evaluation_id' => $evaluation->getId(),
                    'comments' => $comments,
                ]);

                $this->skillsService->rejectAssessment($evaluation, $comments);

                $this->logger->info('Skills evaluation rejected successfully', [
                    'user_id' => $userIdentifier,
                    'evaluation_id' => $evaluation->getId(),
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                $this->addFlash('success', 'Évaluation rejetée avec succès.');
            }
        } catch (Exception $e) {
            $this->logger->error('Error in skills evaluation validation', [
                'user_id' => $userIdentifier,
                'evaluation_id' => $evaluation->getId(),
                'action' => $action ?? 'unknown',
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            $this->addFlash('error', 'Erreur lors de la validation : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_alternance_evaluation_skills_show', ['id' => $evaluation->getId()]);
    }

    #[Route('/bulk/actions', name: 'admin_alternance_evaluation_bulk_actions', methods: ['POST'])]
    public function bulkActions(Request $request): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();

        try {
            $evaluationIds = $request->request->all('evaluation_ids');
            $action = $request->request->get('action');
            $type = $request->request->get('type'); // 'progress' or 'skills'

            $this->logger->info('Admin bulk evaluation actions requested', [
                'user_id' => $userIdentifier,
                'evaluation_ids' => $evaluationIds,
                'action' => $action,
                'type' => $type,
                'evaluation_count' => count($evaluationIds),
                'ip_address' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
            ]);

            if (empty($evaluationIds) || !$action || !$type) {
                $this->logger->warning('Invalid bulk action parameters', [
                    'user_id' => $userIdentifier,
                    'has_evaluation_ids' => !empty($evaluationIds),
                    'has_action' => !empty($action),
                    'has_type' => !empty($type),
                    'evaluation_ids_count' => count($evaluationIds),
                    'action' => $action,
                    'type' => $type,
                ]);

                $this->addFlash('error', 'Paramètres manquants pour l\'action groupée.');

                return $this->redirectToRoute('admin_alternance_evaluation_index');
            }

            if (!in_array($action, ['approve', 'archive'], true)) {
                $this->logger->warning('Invalid bulk action requested', [
                    'user_id' => $userIdentifier,
                    'invalid_action' => $action,
                    'valid_actions' => ['approve', 'archive'],
                ]);

                $this->addFlash('error', 'Action invalide.');

                return $this->redirectToRoute('admin_alternance_evaluation_index');
            }

            if (!in_array($type, ['progress', 'skills'], true)) {
                $this->logger->warning('Invalid bulk action type requested', [
                    'user_id' => $userIdentifier,
                    'invalid_type' => $type,
                    'valid_types' => ['progress', 'skills'],
                ]);

                $this->addFlash('error', 'Type d\'évaluation invalide.');

                return $this->redirectToRoute('admin_alternance_evaluation_index');
            }

            $processed = 0;
            $errors = [];

            if ($type === 'progress') {
                $this->logger->debug('Processing bulk action for progress evaluations', [
                    'user_id' => $userIdentifier,
                    'evaluation_ids' => $evaluationIds,
                    'action' => $action,
                ]);

                $evaluations = $this->progressRepository->findBy(['id' => $evaluationIds]);

                foreach ($evaluations as $evaluation) {
                    try {
                        if ($action === 'approve') {
                            $this->progressService->approveAssessment($evaluation, 'Validation groupée');
                            $processed++;

                            $this->logger->debug('Progress evaluation approved in bulk action', [
                                'user_id' => $userIdentifier,
                                'evaluation_id' => $evaluation->getId(),
                                'student_name' => $evaluation->getStudent()?->getFullName(),
                            ]);
                        } elseif ($action === 'archive') {
                            $evaluation->setIsArchived(true);
                            $processed++;

                            $this->logger->debug('Progress evaluation archived in bulk action', [
                                'user_id' => $userIdentifier,
                                'evaluation_id' => $evaluation->getId(),
                                'student_name' => $evaluation->getStudent()?->getFullName(),
                            ]);
                        }
                    } catch (Exception $e) {
                        $errors[] = "Erreur évaluation #{$evaluation->getId()}: {$e->getMessage()}";

                        $this->logger->error('Error processing progress evaluation in bulk action', [
                            'user_id' => $userIdentifier,
                            'evaluation_id' => $evaluation->getId(),
                            'action' => $action,
                            'error_message' => $e->getMessage(),
                            'error_trace' => $e->getTraceAsString(),
                        ]);
                    }
                }
            } elseif ($type === 'skills') {
                $this->logger->debug('Processing bulk action for skills evaluations', [
                    'user_id' => $userIdentifier,
                    'evaluation_ids' => $evaluationIds,
                    'action' => $action,
                ]);

                $evaluations = $this->skillsRepository->findBy(['id' => $evaluationIds]);

                foreach ($evaluations as $evaluation) {
                    try {
                        if ($action === 'approve') {
                            $this->skillsService->approveAssessment($evaluation, 'Validation groupée');
                            $processed++;

                            $this->logger->debug('Skills evaluation approved in bulk action', [
                                'user_id' => $userIdentifier,
                                'evaluation_id' => $evaluation->getId(),
                                'student_name' => $evaluation->getStudent()?->getFullName(),
                            ]);
                        } elseif ($action === 'archive') {
                            $evaluation->setIsArchived(true);
                            $processed++;

                            $this->logger->debug('Skills evaluation archived in bulk action', [
                                'user_id' => $userIdentifier,
                                'evaluation_id' => $evaluation->getId(),
                                'student_name' => $evaluation->getStudent()?->getFullName(),
                            ]);
                        }
                    } catch (Exception $e) {
                        $errors[] = "Erreur évaluation #{$evaluation->getId()}: {$e->getMessage()}";

                        $this->logger->error('Error processing skills evaluation in bulk action', [
                            'user_id' => $userIdentifier,
                            'evaluation_id' => $evaluation->getId(),
                            'action' => $action,
                            'error_message' => $e->getMessage(),
                            'error_trace' => $e->getTraceAsString(),
                        ]);
                    }
                }
            }

            $this->entityManager->flush();

            $this->logger->info('Bulk evaluation action completed', [
                'user_id' => $userIdentifier,
                'action' => $action,
                'type' => $type,
                'processed_count' => $processed,
                'error_count' => count($errors),
                'total_requested' => count($evaluationIds),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            if ($processed > 0) {
                $this->addFlash('success', sprintf('%d évaluation(s) traitée(s) avec succès.', $processed));
            }

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('warning', $error);
                }
            }
        } catch (Exception $e) {
            $this->logger->error('Error in bulk evaluation actions', [
                'user_id' => $userIdentifier,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'request_parameters' => $request->request->all(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            $this->addFlash('error', 'Erreur lors du traitement : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_alternance_evaluation_index');
    }

    #[Route('/analytics', name: 'admin_alternance_evaluation_analytics', methods: ['GET'])]
    public function analytics(Request $request): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();

        try {
            $period = $request->query->get('period', '30'); // days
            $formation = $request->query->get('formation');

            $this->logger->info('Admin evaluation analytics page accessed', [
                'user_id' => $userIdentifier,
                'period' => $period,
                'formation' => $formation,
                'ip_address' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
            ]);

            $this->logger->debug('Generating evaluation analytics', [
                'user_id' => $userIdentifier,
                'period_days' => $period,
                'formation_filter' => $formation,
            ]);

            $analytics = $this->getEvaluationAnalytics($period, $formation);

            $this->logger->debug('Evaluation analytics generated successfully', [
                'user_id' => $userIdentifier,
                'analytics_keys' => array_keys($analytics),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            $this->logger->info('Admin evaluation analytics page rendered successfully', [
                'user_id' => $userIdentifier,
                'period' => $period,
                'formation' => $formation,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            return $this->render('admin/alternance/evaluation/analytics.html.twig', [
                'analytics' => $analytics,
                'period' => $period,
                'formation' => $formation,
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Error in admin evaluation analytics', [
                'user_id' => $userIdentifier,
                'period' => $period ?? 'unknown',
                'formation' => $formation ?? 'unknown',
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'request_parameters' => $request->query->all(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la génération des analyses. Veuillez réessayer.');

            // Return a minimal error page with empty data
            return $this->render('admin/alternance/evaluation/analytics.html.twig', [
                'analytics' => [],
                'period' => $period,
                'formation' => $formation,
            ]);
        }
    }

    #[Route('/export', name: 'admin_alternance_evaluation_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();

        try {
            $format = $request->query->get('format', 'csv');
            $type = $request->query->get('type', 'all'); // 'progress', 'skills', or 'all'
            $filters = [
                'status' => $request->query->get('status', ''),
                'period' => $request->query->get('period', ''),
            ];

            $this->logger->info('Admin evaluation export requested', [
                'user_id' => $userIdentifier,
                'format' => $format,
                'type' => $type,
                'filters' => $filters,
                'ip_address' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
            ]);

            if (!in_array($format, ['csv'], true)) {
                $this->logger->warning('Invalid export format requested', [
                    'user_id' => $userIdentifier,
                    'invalid_format' => $format,
                    'valid_formats' => ['csv'],
                ]);

                $this->addFlash('error', 'Format d\'export non supporté.');

                return $this->redirectToRoute('admin_alternance_evaluation_index');
            }

            if (!in_array($type, ['progress', 'skills', 'all'], true)) {
                $this->logger->warning('Invalid export type requested', [
                    'user_id' => $userIdentifier,
                    'invalid_type' => $type,
                    'valid_types' => ['progress', 'skills', 'all'],
                ]);

                $this->addFlash('error', 'Type d\'évaluation invalide.');

                return $this->redirectToRoute('admin_alternance_evaluation_index');
            }

            $this->logger->debug('Generating evaluation export data', [
                'user_id' => $userIdentifier,
                'format' => $format,
                'type' => $type,
                'filters' => $filters,
            ]);

            $data = $this->exportEvaluations($type, $filters, $format);

            $this->logger->info('Evaluation export generated successfully', [
                'user_id' => $userIdentifier,
                'format' => $format,
                'type' => $type,
                'data_size_bytes' => strlen($data),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            $response = new Response($data);
            $response->headers->set('Content-Type', $format === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->headers->set('Content-Disposition', 'attachment; filename="evaluations_export.' . $format . '"');

            return $response;
        } catch (Exception $e) {
            $this->logger->error('Error in evaluation export', [
                'user_id' => $userIdentifier,
                'format' => $format ?? 'unknown',
                'type' => $type ?? 'unknown',
                'filters' => $filters ?? [],
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'request_parameters' => $request->query->all(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            $this->addFlash('error', 'Erreur lors de l\'export : ' . $e->getMessage());

            return $this->redirectToRoute('admin_alternance_evaluation_index');
        }
    }

    private function getEvaluationStatistics(): array
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();

        try {
            $this->logger->debug('Fetching evaluation statistics from repositories', [
                'user_id' => $userIdentifier,
            ]);

            $progressStats = $this->progressRepository->getStatistics();
            $skillsStats = $this->skillsRepository->getStatistics();

            $this->logger->debug('Repository statistics retrieved', [
                'user_id' => $userIdentifier,
                'progress_stats' => $progressStats,
                'skills_stats' => $skillsStats,
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            $this->logger->debug('Fetching recent evaluation activity', [
                'user_id' => $userIdentifier,
            ]);

            $recentActivity = $this->getRecentEvaluationActivity();

            $statistics = [
                'total_evaluations' => $progressStats['total'] + $skillsStats['total'],
                'pending_validation' => $progressStats['pending'] + $skillsStats['pending'],
                'validated_this_month' => $progressStats['validated_this_month'] + $skillsStats['validated_this_month'],
                'average_score' => ($progressStats['average_score'] + $skillsStats['average_score']) / 2,
                'progress_evaluations' => $progressStats,
                'skills_evaluations' => $skillsStats,
                'recent_activity' => $recentActivity,
            ];

            $this->logger->debug('Evaluation statistics computed successfully', [
                'user_id' => $userIdentifier,
                'total_evaluations' => $statistics['total_evaluations'],
                'pending_validation' => $statistics['pending_validation'],
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $statistics;
        } catch (Throwable $e) {
            $this->logger->error('Error in getEvaluationStatistics', [
                'user_id' => $userIdentifier,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            // Return empty statistics to avoid breaking the page
            return [
                'total_evaluations' => 0,
                'pending_validation' => 0,
                'validated_this_month' => 0,
                'average_score' => 0,
                'progress_evaluations' => [],
                'skills_evaluations' => [],
                'recent_activity' => [],
            ];
        }
    }

    private function getRecentEvaluationActivity(): array
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();

        try {
            $this->logger->debug('Fetching recent evaluation activity', [
                'user_id' => $userIdentifier,
            ]);

            $recentProgress = $this->progressRepository->findBy([], ['createdAt' => 'DESC'], 5);
            $recentSkills = $this->skillsRepository->findBy([], ['createdAt' => 'DESC'], 5);

            $this->logger->debug('Recent evaluations retrieved', [
                'user_id' => $userIdentifier,
                'progress_count' => count($recentProgress),
                'skills_count' => count($recentSkills),
            ]);

            $activity = [];

            foreach ($recentProgress as $evaluation) {
                $activity[] = [
                    'type' => 'progress',
                    'evaluation' => $evaluation,
                    'date' => $evaluation->getCreatedAt(),
                ];
            }

            foreach ($recentSkills as $evaluation) {
                $activity[] = [
                    'type' => 'skills',
                    'evaluation' => $evaluation,
                    'date' => $evaluation->getCreatedAt(),
                ];
            }

            // Sort by date
            usort($activity, static fn ($a, $b) => $b['date'] <=> $a['date']);

            $activitySlice = array_slice($activity, 0, 10);

            $this->logger->debug('Recent evaluation activity processed successfully', [
                'user_id' => $userIdentifier,
                'total_activity_items' => count($activity),
                'returned_activity_items' => count($activitySlice),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $activitySlice;
        } catch (Throwable $e) {
            $this->logger->error('Error in getRecentEvaluationActivity', [
                'user_id' => $userIdentifier,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            // Return empty activity to avoid breaking the page
            return [];
        }
    }

    private function getEvaluationAnalytics(string $period, ?string $formation): array
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();

        try {
            $days = (int) $period;

            if ($days <= 0 || $days > 365) {
                $this->logger->warning('Invalid analytics period requested', [
                    'user_id' => $userIdentifier,
                    'requested_period' => $period,
                    'parsed_days' => $days,
                    'valid_range' => '1-365 days',
                ]);

                $days = 30; // Default fallback
            }

            $startDate = new DateTime("-{$days} days");

            $this->logger->debug('Generating evaluation analytics', [
                'user_id' => $userIdentifier,
                'period_days' => $days,
                'start_date' => $startDate->format('Y-m-d H:i:s'),
                'formation_filter' => $formation,
            ]);

            $this->logger->debug('Fetching evaluation trends', [
                'user_id' => $userIdentifier,
                'start_date' => $startDate->format('Y-m-d'),
            ]);

            $evaluationTrends = $this->progressRepository->getEvaluationTrends($startDate);

            $this->logger->debug('Fetching score distribution', [
                'user_id' => $userIdentifier,
                'start_date' => $startDate->format('Y-m-d'),
            ]);

            $scoreDistribution = $this->progressRepository->getScoreDistribution($startDate);

            $this->logger->debug('Fetching completion rates', [
                'user_id' => $userIdentifier,
                'start_date' => $startDate->format('Y-m-d'),
            ]);

            $completionRates = $this->progressRepository->getCompletionRates($startDate);

            $this->logger->debug('Fetching mentor performance metrics', [
                'user_id' => $userIdentifier,
                'start_date' => $startDate->format('Y-m-d'),
            ]);

            $mentorPerformance = $this->progressRepository->getMentorPerformanceMetrics($startDate);

            $this->logger->debug('Fetching skills progression', [
                'user_id' => $userIdentifier,
                'start_date' => $startDate->format('Y-m-d'),
            ]);

            $skillsProgression = $this->skillsRepository->getSkillsProgression($startDate);

            $this->logger->debug('Generating recommendations', [
                'user_id' => $userIdentifier,
                'start_date' => $startDate->format('Y-m-d'),
            ]);

            $recommendations = $this->generateRecommendations($startDate);

            $analytics = [
                'evaluation_trends' => $evaluationTrends,
                'score_distribution' => $scoreDistribution,
                'completion_rates' => $completionRates,
                'mentor_performance' => $mentorPerformance,
                'skills_progression' => $skillsProgression,
                'recommendations' => $recommendations,
            ];

            $this->logger->info('Evaluation analytics generated successfully', [
                'user_id' => $userIdentifier,
                'period_days' => $days,
                'formation_filter' => $formation,
                'analytics_sections' => array_keys($analytics),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            return $analytics;
        } catch (Throwable $e) {
            $this->logger->error('Error in getEvaluationAnalytics', [
                'user_id' => $userIdentifier,
                'period' => $period,
                'formation' => $formation,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            // Return empty analytics to avoid breaking the page
            return [
                'evaluation_trends' => [],
                'score_distribution' => [],
                'completion_rates' => [],
                'mentor_performance' => [],
                'skills_progression' => [],
                'recommendations' => [],
            ];
        }
    }

    private function generateRecommendations(DateTime $startDate): array
    {
        // Generate recommendations as a simple array of strings for the template
        return [
            'Organiser des sessions de formation pour les mentors ayant des scores inférieurs à 70%',
            'Mettre en place un suivi renforcé pour les 3 alternants en difficulté identifiés',
            'Développer des modules spécifiques sur les compétences Communication et Gestion de projet',
            'Réviser les critères d\'évaluation pour homogénéiser les pratiques entre mentors',
            'Planifier des entretiens individuels avec les alternants à risque de décrochage',
        ];
    }

    private function exportEvaluations(string $type, array $filters, string $format): string
    {
        $startTime = microtime(true);
        $userIdentifier = $this->getUser()?->getUserIdentifier();

        try {
            $this->logger->debug('Starting evaluation export generation', [
                'user_id' => $userIdentifier,
                'type' => $type,
                'filters' => $filters,
                'format' => $format,
            ]);

            $evaluations = [];

            if ($type === 'progress' || $type === 'all') {
                $this->logger->debug('Exporting progress evaluations', [
                    'user_id' => $userIdentifier,
                    'filters' => $filters,
                ]);

                $progressEvaluations = $this->progressRepository->findForExport($filters);

                $this->logger->debug('Progress evaluations retrieved for export', [
                    'user_id' => $userIdentifier,
                    'count' => count($progressEvaluations),
                ]);

                foreach ($progressEvaluations as $eval) {
                    $evaluations[] = [
                        'type' => 'Évaluation de progression',
                        'student' => $eval->getStudent()->getFullName(),
                        'mentor' => $eval->getMentor() ? $eval->getMentor()->getFullName() : '',
                        'date' => $eval->getCreatedAt()->format('d/m/Y'),
                        'status' => $eval->getStatus(),
                        'score' => $eval->getOverallScore(),
                    ];
                }
            }

            if ($type === 'skills' || $type === 'all') {
                $this->logger->debug('Exporting skills evaluations', [
                    'user_id' => $userIdentifier,
                    'filters' => $filters,
                ]);

                $skillsEvaluations = $this->skillsRepository->findForExport($filters);

                $this->logger->debug('Skills evaluations retrieved for export', [
                    'user_id' => $userIdentifier,
                    'count' => count($skillsEvaluations),
                ]);

                foreach ($skillsEvaluations as $eval) {
                    $evaluations[] = [
                        'type' => 'Évaluation de compétences',
                        'student' => $eval->getStudent()->getFullName(),
                        'mentor' => $eval->getMentor() ? $eval->getMentor()->getFullName() : '',
                        'date' => $eval->getCreatedAt()->format('d/m/Y'),
                        'status' => $eval->getStatus(),
                        'score' => $eval->getOverallScore(),
                    ];
                }
            }

            $this->logger->debug('All evaluations prepared for export', [
                'user_id' => $userIdentifier,
                'total_evaluations' => count($evaluations),
                'format' => $format,
            ]);

            if ($format === 'csv') {
                $this->logger->debug('Generating CSV export', [
                    'user_id' => $userIdentifier,
                    'evaluations_count' => count($evaluations),
                ]);

                $output = fopen('php://temp', 'r+');

                if ($output === false) {
                    throw new Exception('Unable to create temporary file for CSV export');
                }

                // Headers
                fputcsv($output, [
                    'Type d\'évaluation',
                    'Alternant',
                    'Mentor',
                    'Date',
                    'Statut',
                    'Score',
                ]);

                // Data
                foreach ($evaluations as $evaluation) {
                    fputcsv($output, $evaluation);
                }

                rewind($output);
                $content = stream_get_contents($output);
                fclose($output);

                if ($content === false) {
                    throw new Exception('Unable to read CSV content from temporary file');
                }

                $this->logger->info('CSV export generated successfully', [
                    'user_id' => $userIdentifier,
                    'evaluations_count' => count($evaluations),
                    'file_size_bytes' => strlen($content),
                    'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                return $content;
            }

            throw new InvalidArgumentException("Format d'export non supporté: {$format}");
        } catch (Throwable $e) {
            $this->logger->error('Error in exportEvaluations', [
                'user_id' => $userIdentifier,
                'type' => $type,
                'filters' => $filters,
                'format' => $format,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'execution_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            throw $e; // Re-throw to let the calling method handle it
        }
    }
}
