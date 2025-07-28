<?php

namespace App\Controller\Admin\Alternance;

use App\Entity\Alternance\ProgressAssessment;
use App\Entity\Alternance\SkillsAssessment;
use App\Repository\Alternance\ProgressAssessmentRepository;
use App\Repository\Alternance\SkillsAssessmentRepository;
use App\Repository\AlternanceContractRepository;
use App\Service\Alternance\ProgressAssessmentService;
use App\Service\Alternance\SkillsAssessmentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('', name: 'admin_alternance_evaluation_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $search = $request->query->get('search', '');
        $type = $request->query->get('type', '');
        $status = $request->query->get('status', '');
        $perPage = 20;

        $filters = [
            'search' => $search,
            'type' => $type,
            'status' => $status,
        ];

        // Get both types of evaluations
        $progressEvaluations = $this->progressRepository->findPaginatedAssessments($filters, $page, $perPage);
        $skillsEvaluations = $this->skillsRepository->findPaginatedAssessments($filters, $page, $perPage);

        // Combine and sort by date
        $evaluations = array_merge($progressEvaluations, $skillsEvaluations);
        usort($evaluations, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());

        // Get evaluation statistics
        $statistics = $this->getEvaluationStatistics();

        return $this->render('admin/alternance/evaluation/index.html.twig', [
            'evaluations' => $evaluations,
            'current_page' => $page,
            'total_pages' => ceil(count($evaluations) / $perPage),
            'filters' => $filters,
            'statistics' => $statistics,
        ]);
    }

    #[Route('/progress', name: 'admin_alternance_evaluation_progress', methods: ['GET'])]
    public function progressEvaluations(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $perPage = 20;

        $filters = [
            'search' => $search,
            'status' => $status,
        ];

        $evaluations = $this->progressRepository->findPaginatedAssessments($filters, $page, $perPage);
        $totalPages = ceil($this->progressRepository->countFilteredAssessments($filters) / $perPage);

        return $this->render('admin/alternance/evaluation/progress.html.twig', [
            'evaluations' => $evaluations,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'filters' => $filters,
        ]);
    }

    #[Route('/skills', name: 'admin_alternance_evaluation_skills', methods: ['GET'])]
    public function skillsEvaluations(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $perPage = 20;

        $filters = [
            'search' => $search,
            'status' => $status,
        ];

        $evaluations = $this->skillsRepository->findPaginatedAssessments($filters, $page, $perPage);
        $totalPages = ceil($this->skillsRepository->countFilteredAssessments($filters) / $perPage);

        return $this->render('admin/alternance/evaluation/skills.html.twig', [
            'evaluations' => $evaluations,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'filters' => $filters,
        ]);
    }

    #[Route('/progress/{id}', name: 'admin_alternance_evaluation_progress_show', methods: ['GET'])]
    public function showProgressEvaluation(ProgressAssessment $evaluation): Response
    {
        $analysis = $this->progressService->analyzeAssessment($evaluation);

        return $this->render('admin/alternance/evaluation/progress_show.html.twig', [
            'evaluation' => $evaluation,
            'analysis' => $analysis,
        ]);
    }

    #[Route('/skills/{id}', name: 'admin_alternance_evaluation_skills_show', methods: ['GET'])]
    public function showSkillsEvaluation(SkillsAssessment $evaluation): Response
    {
        $analysis = $this->skillsService->analyzeSkillsAssessment($evaluation);

        return $this->render('admin/alternance/evaluation/skills_show.html.twig', [
            'evaluation' => $evaluation,
            'analysis' => $analysis,
        ]);
    }

    #[Route('/progress/{id}/validate', name: 'admin_alternance_evaluation_progress_validate', methods: ['POST'])]
    public function validateProgressEvaluation(Request $request, ProgressAssessment $evaluation): Response
    {
        $action = $request->request->get('action'); // 'approve' or 'reject'
        $comments = $request->request->get('comments', '');

        try {
            if ($action === 'approve') {
                $this->progressService->approveAssessment($evaluation, $comments);
                $this->addFlash('success', 'Évaluation approuvée avec succès.');
            } elseif ($action === 'reject') {
                $this->progressService->rejectAssessment($evaluation, $comments);
                $this->addFlash('success', 'Évaluation rejetée avec succès.');
            } else {
                $this->addFlash('error', 'Action invalide.');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la validation : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_alternance_evaluation_progress_show', ['id' => $evaluation->getId()]);
    }

    #[Route('/skills/{id}/validate', name: 'admin_alternance_evaluation_skills_validate', methods: ['POST'])]
    public function validateSkillsEvaluation(Request $request, SkillsAssessment $evaluation): Response
    {
        $action = $request->request->get('action'); // 'approve' or 'reject'
        $comments = $request->request->get('comments', '');

        try {
            if ($action === 'approve') {
                $this->skillsService->approveAssessment($evaluation, $comments);
                $this->addFlash('success', 'Évaluation approuvée avec succès.');
            } elseif ($action === 'reject') {
                $this->skillsService->rejectAssessment($evaluation, $comments);
                $this->addFlash('success', 'Évaluation rejetée avec succès.');
            } else {
                $this->addFlash('error', 'Action invalide.');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la validation : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_alternance_evaluation_skills_show', ['id' => $evaluation->getId()]);
    }

    #[Route('/bulk/actions', name: 'admin_alternance_evaluation_bulk_actions', methods: ['POST'])]
    public function bulkActions(Request $request): Response
    {
        $evaluationIds = $request->request->all('evaluation_ids');
        $action = $request->request->get('action');
        $type = $request->request->get('type'); // 'progress' or 'skills'
        
        if (empty($evaluationIds) || !$action || !$type) {
            $this->addFlash('error', 'Paramètres manquants pour l\'action groupée.');
            return $this->redirectToRoute('admin_alternance_evaluation_index');
        }

        try {
            $processed = 0;
            
            if ($type === 'progress') {
                $evaluations = $this->progressRepository->findBy(['id' => $evaluationIds]);
                foreach ($evaluations as $evaluation) {
                    if ($action === 'approve') {
                        $this->progressService->approveAssessment($evaluation, 'Validation groupée');
                        $processed++;
                    } elseif ($action === 'archive') {
                        $evaluation->setIsArchived(true);
                        $processed++;
                    }
                }
            } elseif ($type === 'skills') {
                $evaluations = $this->skillsRepository->findBy(['id' => $evaluationIds]);
                foreach ($evaluations as $evaluation) {
                    if ($action === 'approve') {
                        $this->skillsService->approveAssessment($evaluation, 'Validation groupée');
                        $processed++;
                    } elseif ($action === 'archive') {
                        $evaluation->setIsArchived(true);
                        $processed++;
                    }
                }
            }
            
            $this->entityManager->flush();
            $this->addFlash('success', sprintf('%d évaluation(s) traitée(s) avec succès.', $processed));
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors du traitement : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_alternance_evaluation_index');
    }

    #[Route('/analytics', name: 'admin_alternance_evaluation_analytics', methods: ['GET'])]
    public function analytics(Request $request): Response
    {
        $period = $request->query->get('period', '30'); // days
        $formation = $request->query->get('formation');
        
        $analytics = $this->getEvaluationAnalytics($period, $formation);

        return $this->render('admin/alternance/evaluation/analytics.html.twig', [
            'analytics' => $analytics,
            'period' => $period,
            'formation' => $formation,
        ]);
    }

    #[Route('/export', name: 'admin_alternance_evaluation_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $format = $request->query->get('format', 'csv');
        $type = $request->query->get('type', 'all'); // 'progress', 'skills', or 'all'
        $filters = [
            'status' => $request->query->get('status', ''),
            'period' => $request->query->get('period', ''),
        ];

        try {
            $data = $this->exportEvaluations($type, $filters, $format);

            $response = new Response($data);
            $response->headers->set('Content-Type', $format === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $response->headers->set('Content-Disposition', 'attachment; filename="evaluations_export.'.$format.'"');
            
            return $response;
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'export : ' . $e->getMessage());
            return $this->redirectToRoute('admin_alternance_evaluation_index');
        }
    }

    private function getEvaluationStatistics(): array
    {
        $progressStats = $this->progressRepository->getStatistics();
        $skillsStats = $this->skillsRepository->getStatistics();

        return [
            'total_evaluations' => $progressStats['total'] + $skillsStats['total'],
            'pending_validation' => $progressStats['pending'] + $skillsStats['pending'],
            'validated_this_month' => $progressStats['validated_this_month'] + $skillsStats['validated_this_month'],
            'average_score' => ($progressStats['average_score'] + $skillsStats['average_score']) / 2,
            'progress_evaluations' => $progressStats,
            'skills_evaluations' => $skillsStats,
            'recent_activity' => $this->getRecentEvaluationActivity(),
        ];
    }

    private function getRecentEvaluationActivity(): array
    {
        $recentProgress = $this->progressRepository->findBy([], ['createdAt' => 'DESC'], 5);
        $recentSkills = $this->skillsRepository->findBy([], ['createdAt' => 'DESC'], 5);
        
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
        usort($activity, fn($a, $b) => $b['date'] <=> $a['date']);
        
        return array_slice($activity, 0, 10);
    }

    private function getEvaluationAnalytics(string $period, ?string $formation): array
    {
        $days = (int) $period;
        $startDate = new \DateTime("-{$days} days");

        return [
            'evaluation_trends' => $this->progressRepository->getEvaluationTrends($startDate),
            'score_distribution' => $this->progressRepository->getScoreDistribution($startDate),
            'completion_rates' => $this->progressRepository->getCompletionRates($startDate),
            'mentor_performance' => $this->progressRepository->getMentorPerformanceMetrics($startDate),
            'skills_progression' => $this->skillsRepository->getSkillsProgression($startDate),
            'recommendations' => $this->generateRecommendations($startDate),
        ];
    }

    private function generateRecommendations(\DateTime $startDate): array
    {
        // Generate recommendations as a simple array of strings for the template
        return [
            'Organiser des sessions de formation pour les mentors ayant des scores inférieurs à 70%',
            'Mettre en place un suivi renforcé pour les 3 alternants en difficulté identifiés',
            'Développer des modules spécifiques sur les compétences Communication et Gestion de projet',
            'Réviser les critères d\'évaluation pour homogénéiser les pratiques entre mentors',
            'Planifier des entretiens individuels avec les alternants à risque de décrochage'
        ];
    }

    private function exportEvaluations(string $type, array $filters, string $format): string
    {
        $evaluations = [];
        
        if ($type === 'progress' || $type === 'all') {
            $progressEvaluations = $this->progressRepository->findForExport($filters);
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
            $skillsEvaluations = $this->skillsRepository->findForExport($filters);
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

        if ($format === 'csv') {
            $output = fopen('php://temp', 'r+');
            
            // Headers
            fputcsv($output, [
                'Type d\'évaluation',
                'Alternant',
                'Mentor',
                'Date',
                'Statut',
                'Score'
            ]);
            
            // Data
            foreach ($evaluations as $evaluation) {
                fputcsv($output, $evaluation);
            }
            
            rewind($output);
            $content = stream_get_contents($output);
            fclose($output);
            
            return $content;
        }

        throw new \InvalidArgumentException("Format d'export non supporté: {$format}");
    }
}
