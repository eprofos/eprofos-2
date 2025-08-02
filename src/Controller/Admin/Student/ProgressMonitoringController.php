<?php

declare(strict_types=1);

namespace App\Controller\Admin\Student;

use App\Entity\Core\StudentEnrollment;
use App\Repository\Core\StudentEnrollmentRepository;
use App\Repository\Core\StudentProgressRepository;
use App\Repository\Training\FormationRepository;
use App\Service\Student\StudentEnrollmentService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * ProgressMonitoringController provides detailed progress oversight for administrators.
 *
 * Handles student progress monitoring, risk detection, intervention management,
 * and progress analytics for administrative oversight and support.
 */
#[Route('/admin/student/progress')]
#[IsGranted('ROLE_ADMIN')]
class ProgressMonitoringController extends AbstractController
{
    public function __construct(
        private StudentEnrollmentRepository $enrollmentRepository,
        private StudentProgressRepository $progressRepository,
        private FormationRepository $formationRepository,
        private StudentEnrollmentService $enrollmentService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Progress monitoring dashboard.
     */
    #[Route('/', name: 'admin_progress_monitoring_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->logger->info('Admin progress monitoring dashboard accessed', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'filters' => $request->query->all(),
            'ip' => $request->getClientIp(),
        ]);

        try {
            // Get filter parameters
            $formation = $request->query->get('formation');
            $riskLevel = $request->query->get('risk_level');
            $progressRange = $request->query->get('progress_range');

            $this->logger->debug('Processing progress monitoring filters', [
                'formation' => $formation,
                'risk_level' => $riskLevel,
                'progress_range' => $progressRange,
            ]);

            // Get overview statistics
            $stats = $this->getProgressOverviewStats();
            $this->logger->debug('Progress overview stats calculated', ['stats' => $stats]);

            // Get at-risk students
            $atRiskStudents = $this->enrollmentRepository->findAtRiskEnrollments();
            $this->logger->debug('At-risk students retrieved', ['count' => count($atRiskStudents)]);

            // Get students with low progress
            $lowProgressStudents = $this->findLowProgressStudents($formation);
            $this->logger->debug('Low progress students retrieved', ['count' => count($lowProgressStudents)]);

            // Get overdue enrollments
            $overdueEnrollments = $this->enrollmentRepository->findOverdueEnrollments();
            $this->logger->debug('Overdue enrollments retrieved', ['count' => count($overdueEnrollments)]);

            // Get progress trends
            $progressTrends = $this->getProgressTrends($formation);
            $this->logger->debug('Progress trends calculated', ['trends_count' => count($progressTrends)]);

            // Get formations for filtering
            $formations = $this->formationRepository->findBy(['isActive' => true], ['title' => 'ASC']);
            $this->logger->debug('Active formations retrieved for filtering', ['count' => count($formations)]);

            $this->logger->info('Progress monitoring dashboard data prepared successfully', [
                'stats_total_active' => $stats['total_active'],
                'at_risk_count' => count($atRiskStudents),
                'low_progress_count' => count($lowProgressStudents),
                'overdue_count' => count($overdueEnrollments),
            ]);

            return $this->render('admin/student/progress/index.html.twig', [
                'stats' => $stats,
                'atRiskStudents' => $atRiskStudents,
                'lowProgressStudents' => $lowProgressStudents,
                'overdueEnrollments' => $overdueEnrollments,
                'progressTrends' => $progressTrends,
                'formations' => $formations,
                'filters' => [
                    'formation' => $formation,
                    'risk_level' => $riskLevel,
                    'progress_range' => $progressRange,
                ],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error accessing progress monitoring dashboard', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'filters' => $request->query->all(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement du tableau de bord. Veuillez réessayer.');
            
            // Return minimal dashboard with error state
            return $this->render('admin/student/progress/index.html.twig', [
                'stats' => ['total_active' => 0, 'at_risk' => 0, 'at_risk_percentage' => 0, 'low_progress' => 0, 'high_progress' => 0, 'average_progress' => 0],
                'atRiskStudents' => [],
                'lowProgressStudents' => [],
                'overdueEnrollments' => [],
                'progressTrends' => [],
                'formations' => [],
                'filters' => ['formation' => null, 'risk_level' => null, 'progress_range' => null],
                'error_state' => true,
            ]);
        }
    }

    /**
     * Detailed progress monitoring with filtering.
     */
    #[Route('/detailed', name: 'admin_progress_monitoring_detailed', methods: ['GET'])]
    public function detailed(Request $request, PaginatorInterface $paginator): Response
    {
        $this->logger->info('Admin detailed progress monitoring accessed', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'filters' => $request->query->all(),
            'ip' => $request->getClientIp(),
        ]);

        try {
            $formation = $request->query->get('formation');
            $status = $request->query->get('status', StudentEnrollment::STATUS_ENROLLED);
            $progressFilter = $request->query->get('progress_filter');
            $riskFilter = $request->query->get('risk_filter');
            $search = $request->query->get('search');

            $this->logger->debug('Processing detailed progress monitoring filters', [
                'formation' => $formation,
                'status' => $status,
                'progress_filter' => $progressFilter,
                'risk_filter' => $riskFilter,
                'search' => $search ? '[REDACTED]' : null, // Don't log search terms for privacy
            ]);

            $queryBuilder = $this->enrollmentRepository->createEnrollmentQueryBuilder()
                ->andWhere('se.status = :status')
                ->setParameter('status', $status);

            if ($formation) {
                $queryBuilder->andWhere('f.id = :formation')
                             ->setParameter('formation', $formation);
                $this->logger->debug('Applied formation filter', ['formation_id' => $formation]);
            }

            if ($search) {
                $queryBuilder->andWhere('st.firstName LIKE :search OR st.lastName LIKE :search OR st.email LIKE :search')
                             ->setParameter('search', '%' . $search . '%');
                $this->logger->debug('Applied search filter');
            }

            // Progress filtering
            if ($progressFilter === 'low') {
                $queryBuilder->andWhere('sp.completionPercentage < 25');
                $this->logger->debug('Applied low progress filter');
            } elseif ($progressFilter === 'medium') {
                $queryBuilder->andWhere('sp.completionPercentage BETWEEN 25 AND 75');
                $this->logger->debug('Applied medium progress filter');
            } elseif ($progressFilter === 'high') {
                $queryBuilder->andWhere('sp.completionPercentage > 75');
                $this->logger->debug('Applied high progress filter');
            }

            // Risk filtering
            if ($riskFilter === 'at_risk') {
                $queryBuilder->andWhere('sp.atRiskOfDropout = :at_risk')
                             ->setParameter('at_risk', true);
                $this->logger->debug('Applied at-risk filter');
            } elseif ($riskFilter === 'low_engagement') {
                $queryBuilder->andWhere('sp.engagementLevel < 3');
                $this->logger->debug('Applied low engagement filter');
            }

            $queryBuilder->orderBy('sp.lastActivity', 'DESC');

            $page = $request->query->getInt('page', 1);
            $enrollments = $paginator->paginate(
                $queryBuilder->getQuery(),
                $page,
                20
            );

            $this->logger->debug('Detailed progress monitoring query executed', [
                'total_items' => $enrollments->getTotalItemCount(),
                'current_page' => $page,
                'items_per_page' => 20,
            ]);

            $formations = $this->formationRepository->findBy(['isActive' => true], ['title' => 'ASC']);
            $this->logger->debug('Active formations retrieved for filtering', ['count' => count($formations)]);

            $this->logger->info('Detailed progress monitoring data prepared successfully', [
                'total_enrollments' => $enrollments->getTotalItemCount(),
                'current_page' => $page,
                'applied_filters' => array_filter([
                    'formation' => $formation,
                    'status' => $status !== StudentEnrollment::STATUS_ENROLLED ? $status : null,
                    'progress_filter' => $progressFilter,
                    'risk_filter' => $riskFilter,
                    'search' => $search ? 'applied' : null,
                ]),
            ]);

            return $this->render('admin/student/progress/detailed.html.twig', [
                'enrollments' => $enrollments,
                'formations' => $formations,
                'filters' => [
                    'formation' => $formation,
                    'status' => $status,
                    'progress_filter' => $progressFilter,
                    'risk_filter' => $riskFilter,
                    'search' => $search,
                ],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error in detailed progress monitoring', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'filters' => $request->query->all(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des données détaillées. Veuillez réessayer.');
            
            // Return empty result set on error
            $formations = [];
            try {
                $formations = $this->formationRepository->findBy(['isActive' => true], ['title' => 'ASC']);
            } catch (\Exception $formationError) {
                $this->logger->error('Failed to load formations for error fallback', [
                    'error' => $formationError->getMessage(),
                ]);
            }

            return $this->render('admin/student/progress/detailed.html.twig', [
                'enrollments' => $paginator->paginate([], 1, 20), // Empty paginated result
                'formations' => $formations,
                'filters' => [
                    'formation' => $request->query->get('formation'),
                    'status' => $request->query->get('status', StudentEnrollment::STATUS_ENROLLED),
                    'progress_filter' => $request->query->get('progress_filter'),
                    'risk_filter' => $request->query->get('risk_filter'),
                    'search' => $request->query->get('search'),
                ],
                'error_state' => true,
            ]);
        }
    }

    /**
     * At-risk students monitoring.
     */
    #[Route('/at-risk', name: 'admin_progress_monitoring_at_risk', methods: ['GET'])]
    public function atRisk(Request $request, PaginatorInterface $paginator): Response
    {
        $this->logger->info('Admin at-risk students monitoring accessed', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'filters' => $request->query->all(),
            'ip' => $request->getClientIp(),
        ]);

        try {
            $formation = $request->query->get('formation');
            $riskLevel = $request->query->get('risk_level');

            $this->logger->debug('Processing at-risk students filters', [
                'formation' => $formation,
                'risk_level' => $riskLevel,
            ]);

            $queryBuilder = $this->enrollmentRepository->createEnrollmentQueryBuilder()
                ->andWhere('se.status = :status')
                ->andWhere('sp.atRiskOfDropout = :at_risk')
                ->setParameter('status', StudentEnrollment::STATUS_ENROLLED)
                ->setParameter('at_risk', true);

            if ($formation) {
                $queryBuilder->andWhere('f.id = :formation')
                             ->setParameter('formation', $formation);
                $this->logger->debug('Applied formation filter for at-risk students', ['formation_id' => $formation]);
            }

            if ($riskLevel === 'high') {
                $queryBuilder->andWhere('sp.riskScore >= 70');
                $this->logger->debug('Applied high risk level filter');
            } elseif ($riskLevel === 'medium') {
                $queryBuilder->andWhere('sp.riskScore BETWEEN 40 AND 69');
                $this->logger->debug('Applied medium risk level filter');
            } elseif ($riskLevel === 'low') {
                $queryBuilder->andWhere('sp.riskScore < 40');
                $this->logger->debug('Applied low risk level filter');
            }

            $queryBuilder->orderBy('sp.riskScore', 'DESC');

            $page = $request->query->getInt('page', 1);
            $atRiskEnrollments = $paginator->paginate(
                $queryBuilder->getQuery(),
                $page,
                20
            );

            $this->logger->debug('At-risk enrollments query executed', [
                'total_items' => $atRiskEnrollments->getTotalItemCount(),
                'current_page' => $page,
            ]);

            // Get risk factors analysis
            $riskFactors = $this->getRiskFactorsAnalysis($formation);
            $this->logger->debug('Risk factors analysis completed', [
                'factors_count' => count($riskFactors),
                'top_factor' => !empty($riskFactors) ? array_key_first($riskFactors) : null,
            ]);

            $formations = $this->formationRepository->findBy(['isActive' => true], ['title' => 'ASC']);
            $this->logger->debug('Active formations retrieved for filtering', ['count' => count($formations)]);

            $this->logger->info('At-risk students monitoring data prepared successfully', [
                'total_at_risk' => $atRiskEnrollments->getTotalItemCount(),
                'current_page' => $page,
                'risk_factors_identified' => count($riskFactors),
                'applied_filters' => array_filter([
                    'formation' => $formation,
                    'risk_level' => $riskLevel,
                ]),
            ]);

            return $this->render('admin/student/progress/at_risk.html.twig', [
                'atRiskEnrollments' => $atRiskEnrollments,
                'riskFactors' => $riskFactors,
                'formations' => $formations,
                'filters' => [
                    'formation' => $formation,
                    'risk_level' => $riskLevel,
                ],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error in at-risk students monitoring', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'filters' => $request->query->all(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des étudiants à risque. Veuillez réessayer.');
            
            // Return empty result set on error
            $formations = [];
            try {
                $formations = $this->formationRepository->findBy(['isActive' => true], ['title' => 'ASC']);
            } catch (\Exception $formationError) {
                $this->logger->error('Failed to load formations for at-risk error fallback', [
                    'error' => $formationError->getMessage(),
                ]);
            }

            return $this->render('admin/student/progress/at_risk.html.twig', [
                'atRiskEnrollments' => $paginator->paginate([], 1, 20), // Empty paginated result
                'riskFactors' => [],
                'formations' => $formations,
                'filters' => [
                    'formation' => $request->query->get('formation'),
                    'risk_level' => $request->query->get('risk_level'),
                ],
                'error_state' => true,
            ]);
        }
    }

    /**
     * Individual student progress details.
     */
    #[Route('/{id}', name: 'admin_progress_monitoring_show', methods: ['GET'])]
    public function show(StudentEnrollment $enrollment): Response
    {
        $this->logger->info('Admin viewing individual student progress', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'enrollment_id' => $enrollment->getId(),
            'student_id' => $enrollment->getStudent()?->getId(),
            'formation_id' => $enrollment->getSession()?->getFormation()?->getId(),
        ]);

        try {
            $progress = $enrollment->getProgress();
            
            if (!$progress) {
                $this->logger->warning('No progress data found for enrollment', [
                    'enrollment_id' => $enrollment->getId(),
                    'student_id' => $enrollment->getStudent()?->getId(),
                ]);
                
                $this->addFlash('error', 'Aucune donnée de progression trouvée pour cet étudiant.');
                return $this->redirectToRoute('admin_progress_monitoring_index');
            }

            $this->logger->debug('Progress data retrieved', [
                'enrollment_id' => $enrollment->getId(),
                'completion_percentage' => $progress->getCompletionPercentage(),
                'at_risk' => $progress->isAtRiskOfDropout(),
                'last_activity' => $progress->getLastActivity()?->format('Y-m-d H:i:s'),
            ]);

            // Get detailed progress breakdown
            $progressBreakdown = $this->getProgressBreakdown($progress);
            $this->logger->debug('Progress breakdown calculated', [
                'modules_count' => count($progressBreakdown['modules'] ?? []),
                'chapters_count' => count($progressBreakdown['chapters'] ?? []),
                'total_time_spent' => $progressBreakdown['total_time_spent'],
            ]);

            // Get activity timeline
            $activityTimeline = $this->getActivityTimeline($enrollment);
            $this->logger->debug('Activity timeline generated', ['timeline_entries' => count($activityTimeline)]);

            // Get intervention history
            $interventionHistory = $this->getInterventionHistory($enrollment);
            $this->logger->debug('Intervention history retrieved', ['interventions_count' => count($interventionHistory)]);

            // Get risk assessment
            $riskAssessment = $this->getRiskAssessment($progress);
            $this->logger->debug('Risk assessment completed', [
                'is_at_risk' => $riskAssessment['is_at_risk'],
                'risk_score' => $riskAssessment['risk_score'],
                'last_activity_days' => $riskAssessment['last_activity_days'],
                'engagement_trend' => $riskAssessment['engagement_trend'],
            ]);

            $this->logger->info('Individual student progress data prepared successfully', [
                'enrollment_id' => $enrollment->getId(),
                'completion_percentage' => $progress->getCompletionPercentage(),
                'at_risk_status' => $progress->isAtRiskOfDropout(),
                'interventions_count' => count($interventionHistory),
            ]);

            return $this->render('admin/student/progress/show.html.twig', [
                'enrollment' => $enrollment,
                'progress' => $progress,
                'progressBreakdown' => $progressBreakdown,
                'activityTimeline' => $activityTimeline,
                'interventionHistory' => $interventionHistory,
                'riskAssessment' => $riskAssessment,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error viewing individual student progress', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'enrollment_id' => $enrollment->getId(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des détails de l\'étudiant. Veuillez réessayer.');
            return $this->redirectToRoute('admin_progress_monitoring_index');
        }
    }

    /**
     * Record intervention action.
     */
    #[Route('/{id}/intervention', name: 'admin_progress_monitoring_intervention', methods: ['POST'])]
    public function recordIntervention(StudentEnrollment $enrollment, Request $request): Response
    {
        $this->logger->info('Admin recording intervention for student', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'enrollment_id' => $enrollment->getId(),
            'student_id' => $enrollment->getStudent()?->getId(),
            'ip' => $request->getClientIp(),
        ]);

        try {
            $interventionType = $request->request->get('intervention_type');
            $notes = $request->request->get('notes');
            $followUpDate = $request->request->get('follow_up_date');

            // Validate input data
            if (empty($interventionType) || empty($notes)) {
                throw new \InvalidArgumentException('Type d\'intervention et notes sont requis');
            }

            $this->logger->debug('Processing intervention data', [
                'intervention_type' => $interventionType,
                'notes_length' => strlen($notes),
                'follow_up_date' => $followUpDate,
                'enrollment_id' => $enrollment->getId(),
            ]);

            // For now, add intervention details to enrollment admin notes
            $currentNotes = $enrollment->getAdminNotes() ?? '';
            $interventionNote = sprintf(
                "\n[%s] Intervention %s: %s",
                date('Y-m-d H:i'),
                $interventionType,
                $notes
            );
            
            if ($followUpDate) {
                $interventionNote .= sprintf(" (Suivi prévu: %s)", $followUpDate);
            }
            
            $enrollment->setAdminNotes($currentNotes . $interventionNote);
            $this->entityManager->flush();

            $this->logger->info('Intervention recorded successfully', [
                'enrollment_id' => $enrollment->getId(),
                'intervention_type' => $interventionType,
                'admin_user' => $this->getUser()?->getUserIdentifier(),
                'follow_up_date' => $followUpDate,
            ]);

            $this->addFlash('success', 'Intervention enregistrée avec succès');
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Invalid intervention data provided', [
                'error' => $e->getMessage(),
                'enrollment_id' => $enrollment->getId(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'request_data' => $request->request->all(),
            ]);

            $this->addFlash('error', $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('Error recording intervention', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'enrollment_id' => $enrollment->getId(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'request_data' => $request->request->all(),
            ]);

            $this->addFlash('error', 'Erreur lors de l\'enregistrement : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_progress_monitoring_show', ['id' => $enrollment->getId()]);
    }

    /**
     * Update risk status.
     */
    #[Route('/{id}/risk-status', name: 'admin_progress_monitoring_risk_status', methods: ['POST'])]
    public function updateRiskStatus(StudentEnrollment $enrollment, Request $request): Response
    {
        $this->logger->info('Admin updating risk status for student', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'enrollment_id' => $enrollment->getId(),
            'student_id' => $enrollment->getStudent()?->getId(),
            'ip' => $request->getClientIp(),
        ]);

        try {
            $atRisk = $request->request->getBoolean('at_risk');
            $riskReason = $request->request->get('risk_reason');

            $this->logger->debug('Processing risk status update', [
                'enrollment_id' => $enrollment->getId(),
                'at_risk' => $atRisk,
                'risk_reason' => $riskReason ? '[PROVIDED]' : null,
            ]);

            $progress = $enrollment->getProgress();
            if (!$progress) {
                throw new \Exception('Aucune donnée de progression trouvée');
            }

            $previousRiskStatus = $progress->isAtRiskOfDropout();
            $progress->setAtRiskOfDropout($atRisk);
            
            // Add risk reason to admin notes if needed
            if ($atRisk && $riskReason) {
                $currentNotes = $enrollment->getAdminNotes() ?? '';
                $riskNote = sprintf(
                    "\n[%s] Marqué à risque: %s",
                    date('Y-m-d H:i'),
                    $riskReason
                );
                $enrollment->setAdminNotes($currentNotes . $riskNote);
                
                $this->logger->debug('Risk reason added to admin notes', [
                    'enrollment_id' => $enrollment->getId(),
                ]);
            } elseif (!$atRisk && $previousRiskStatus) {
                // Log when student is removed from at-risk status
                $currentNotes = $enrollment->getAdminNotes() ?? '';
                $riskNote = sprintf(
                    "\n[%s] Retiré du statut à risque par %s",
                    date('Y-m-d H:i'),
                    $this->getUser()?->getUserIdentifier()
                );
                $enrollment->setAdminNotes($currentNotes . $riskNote);
            }

            $this->entityManager->flush();

            $this->logger->info('Risk status updated successfully', [
                'enrollment_id' => $enrollment->getId(),
                'previous_risk_status' => $previousRiskStatus,
                'new_risk_status' => $atRisk,
                'admin_user' => $this->getUser()?->getUserIdentifier(),
                'risk_reason_provided' => !empty($riskReason),
            ]);

            $this->addFlash('success', 'Statut de risque mis à jour avec succès');
        } catch (\Exception $e) {
            $this->logger->error('Error updating risk status', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'enrollment_id' => $enrollment->getId(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'request_data' => $request->request->all(),
            ]);

            $this->addFlash('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_progress_monitoring_show', ['id' => $enrollment->getId()]);
    }

    /**
     * Bulk intervention actions.
     */
    #[Route('/bulk/intervention', name: 'admin_progress_monitoring_bulk_intervention', methods: ['POST'])]
    public function bulkIntervention(Request $request): Response
    {
        $this->logger->info('Admin performing bulk intervention', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'ip' => $request->getClientIp(),
        ]);

        try {
            $enrollmentIds = $request->request->all('enrollment_ids');
            $interventionType = $request->request->get('intervention_type');
            $notes = $request->request->get('notes');

            // Validate input data
            if (empty($enrollmentIds)) {
                throw new \InvalidArgumentException('Aucune inscription sélectionnée');
            }

            if (empty($interventionType) || empty($notes)) {
                throw new \InvalidArgumentException('Type d\'intervention et notes sont requis');
            }

            $this->logger->debug('Processing bulk intervention data', [
                'enrollment_ids_count' => count($enrollmentIds),
                'intervention_type' => $interventionType,
                'notes_length' => strlen($notes),
                'enrollment_ids' => $enrollmentIds,
            ]);

            $enrollments = $this->enrollmentRepository->findBy(['id' => $enrollmentIds]);
            
            if (count($enrollments) !== count($enrollmentIds)) {
                $this->logger->warning('Some enrollments not found for bulk intervention', [
                    'requested_ids' => $enrollmentIds,
                    'found_count' => count($enrollments),
                    'found_ids' => array_map(fn($e) => $e->getId(), $enrollments),
                ]);
            }

            $successCount = 0;
            $errorCount = 0;
            $errors = [];

            foreach ($enrollments as $enrollment) {
                try {
                    $currentNotes = $enrollment->getAdminNotes() ?? '';
                    $interventionNote = sprintf(
                        "\n[%s] Intervention en lot %s: %s (Admin: %s)",
                        date('Y-m-d H:i'),
                        $interventionType,
                        $notes,
                        $this->getUser()?->getUserIdentifier()
                    );
                    
                    $enrollment->setAdminNotes($currentNotes . $interventionNote);
                    $successCount++;

                    $this->logger->debug('Bulk intervention applied to enrollment', [
                        'enrollment_id' => $enrollment->getId(),
                        'student_id' => $enrollment->getStudent()?->getId(),
                    ]);
                } catch (\Exception $e) {
                    $errorCount++;
                    $errors[] = sprintf('Inscription %d: %s', $enrollment->getId(), $e->getMessage());
                    
                    $this->logger->error('Error applying bulk intervention to individual enrollment', [
                        'enrollment_id' => $enrollment->getId(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->entityManager->flush();

            $this->logger->info('Bulk intervention completed', [
                'total_requested' => count($enrollmentIds),
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'intervention_type' => $interventionType,
                'admin_user' => $this->getUser()?->getUserIdentifier(),
            ]);

            if ($successCount > 0) {
                $this->addFlash('success', sprintf(
                    'Intervention appliquée à %d inscription(s)',
                    $successCount
                ));
            }

            if ($errorCount > 0) {
                $this->addFlash('warning', sprintf(
                    '%d erreur(s) lors de l\'application: %s',
                    $errorCount,
                    implode(', ', array_slice($errors, 0, 3)) . ($errorCount > 3 ? '...' : '')
                ));
            }
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Invalid bulk intervention data provided', [
                'error' => $e->getMessage(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'request_data' => $request->request->all(),
            ]);

            $this->addFlash('error', $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('Error performing bulk intervention', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'request_data' => $request->request->all(),
            ]);

            $this->addFlash('error', 'Erreur lors de l\'intervention en lot : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_progress_monitoring_detailed');
    }

    /**
     * API endpoint for progress statistics.
     */
    #[Route('/api/stats', name: 'admin_progress_monitoring_api_stats', methods: ['GET'])]
    public function statsApi(Request $request): JsonResponse
    {
        $this->logger->info('API stats endpoint accessed', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'formation' => $request->query->get('formation'),
            'ip' => $request->getClientIp(),
        ]);

        try {
            $formation = $request->query->get('formation');
            
            $this->logger->debug('Generating stats data', ['formation' => $formation]);
            
            $stats = [
                'overview' => $this->getProgressOverviewStats(),
                'trends' => $this->getProgressTrends($formation),
                'risk_distribution' => $this->getRiskDistribution($formation),
                'completion_forecast' => $this->getCompletionForecast($formation),
            ];

            $this->logger->debug('API stats data generated successfully', [
                'formation' => $formation,
                'overview_total_active' => $stats['overview']['total_active'],
                'trends_count' => count($stats['trends']),
                'risk_distribution_count' => count($stats['risk_distribution']),
            ]);

            return new JsonResponse($stats);
        } catch (\Exception $e) {
            $this->logger->error('Error generating API stats', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'formation' => $request->query->get('formation'),
            ]);

            return new JsonResponse([
                'error' => 'Erreur lors de la génération des statistiques',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API endpoint for progress chart data.
     */
    #[Route('/api/charts/{type}', name: 'admin_progress_monitoring_api_charts', methods: ['GET'])]
    public function chartsApi(string $type, Request $request): JsonResponse
    {
        $this->logger->info('API charts endpoint accessed', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'chart_type' => $type,
            'formation' => $request->query->get('formation'),
            'ip' => $request->getClientIp(),
        ]);

        try {
            $formation = $request->query->get('formation');

            $this->logger->debug('Generating chart data', [
                'type' => $type,
                'formation' => $formation,
            ]);

            $data = match ($type) {
                'progress_distribution' => $this->getProgressDistributionChart($formation),
                'engagement_levels' => $this->getEngagementLevelsChart($formation),
                'risk_factors' => $this->getRiskFactorsChart($formation),
                'completion_trends' => $this->getCompletionTrendsChart($formation),
                default => throw new \InvalidArgumentException('Type de graphique non supporté: ' . $type)
            };

            $this->logger->debug('Chart data generated successfully', [
                'type' => $type,
                'formation' => $formation,
                'data_points' => count($data),
            ]);

            return new JsonResponse($data);
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Invalid chart type requested', [
                'type' => $type,
                'error' => $e->getMessage(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            return new JsonResponse([
                'error' => 'Type de graphique invalide',
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            $this->logger->error('Error generating chart data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'type' => $type,
                'formation' => $request->query->get('formation'),
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            return new JsonResponse([
                'error' => 'Erreur lors de la génération des données du graphique',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get progress overview statistics.
     */
    private function getProgressOverviewStats(): array
    {
        try {
            $this->logger->debug('Calculating progress overview statistics');

            $totalActive = $this->enrollmentRepository->count(['status' => StudentEnrollment::STATUS_ENROLLED]);
            $atRisk = $this->enrollmentRepository->count(['status' => StudentEnrollment::STATUS_ENROLLED, 'progress.atRiskOfDropout' => true]);
            $lowProgress = $this->progressRepository->countByProgressRange(0, 25);
            $highProgress = $this->progressRepository->countByProgressRange(75, 100);
            $averageProgress = $this->progressRepository->getAverageProgress();

            $stats = [
                'total_active' => $totalActive,
                'at_risk' => $atRisk,
                'at_risk_percentage' => $totalActive > 0 ? round(($atRisk / $totalActive) * 100, 1) : 0,
                'low_progress' => $lowProgress,
                'high_progress' => $highProgress,
                'average_progress' => $averageProgress,
            ];

            $this->logger->debug('Progress overview statistics calculated', $stats);

            return $stats;
        } catch (\Exception $e) {
            $this->logger->error('Error calculating progress overview statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return default values on error
            return [
                'total_active' => 0,
                'at_risk' => 0,
                'at_risk_percentage' => 0,
                'low_progress' => 0,
                'high_progress' => 0,
                'average_progress' => 0,
            ];
        }
    }

    /**
     * Find students with low progress.
     */
    private function findLowProgressStudents(?string $formationId): array
    {
        try {
            $this->logger->debug('Finding students with low progress', ['formation_id' => $formationId]);

            $queryBuilder = $this->enrollmentRepository->createEnrollmentQueryBuilder()
                ->andWhere('se.status = :status')
                ->andWhere('sp.completionPercentage < 25')
                ->setParameter('status', StudentEnrollment::STATUS_ENROLLED);

            if ($formationId) {
                $queryBuilder->andWhere('f.id = :formation')
                             ->setParameter('formation', $formationId);
                $this->logger->debug('Applied formation filter for low progress students', ['formation_id' => $formationId]);
            }

            $results = $queryBuilder->orderBy('sp.completionPercentage', 'ASC')
                               ->setMaxResults(10)
                               ->getQuery()
                               ->getResult();

            $this->logger->debug('Low progress students found', [
                'count' => count($results),
                'formation_id' => $formationId,
            ]);

            return $results;
        } catch (\Exception $e) {
            $this->logger->error('Error finding low progress students', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'formation_id' => $formationId,
            ]);

            return [];
        }
    }

    /**
     * Get progress trends over time.
     */
    private function getProgressTrends(?string $formationId): array
    {
        try {
            $this->logger->debug('Calculating progress trends', ['formation_id' => $formationId]);

            $sql = "
                SELECT 
                    DATE(sp.last_activity) as date,
                    AVG(sp.completion_percentage) as avg_progress,
                    COUNT(CASE WHEN sp.at_risk_of_dropout = true THEN 1 END) as at_risk_count,
                    COUNT(*) as total_students
                FROM student_progress sp
                LEFT JOIN student_enrollments se ON sp.id = se.progress_id
                LEFT JOIN session_registrations sr ON se.session_registration_id = sr.id
                LEFT JOIN sessions s ON sr.session_id = s.id
                WHERE se.status = 'enrolled'
                  AND sp.last_activity >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ";

            $params = [];
            if ($formationId) {
                $sql .= " AND s.formation_id = :formation_id";
                $params['formation_id'] = $formationId;
            }

            $sql .= " GROUP BY DATE(sp.last_activity) ORDER BY date DESC LIMIT 30";

            $stmt = $this->entityManager->getConnection()->prepare($sql);
            $results = $stmt->executeQuery($params)->fetchAllAssociative();

            $this->logger->debug('Progress trends calculated', [
                'formation_id' => $formationId,
                'trends_count' => count($results),
                'date_range' => !empty($results) ? [
                    'from' => end($results)['date'] ?? null,
                    'to' => $results[0]['date'] ?? null,
                ] : null,
            ]);

            return $results;
        } catch (\Exception $e) {
            $this->logger->error('Error calculating progress trends', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'formation_id' => $formationId,
            ]);

            return [];
        }
    }

    /**
     * Get risk factors analysis.
     */
    private function getRiskFactorsAnalysis(?string $formationId): array
    {
        try {
            $this->logger->debug('Analyzing risk factors', ['formation_id' => $formationId]);

            $sql = "
                SELECT 
                    sp.risk_factors,
                    COUNT(*) as count
                FROM student_progress sp
                LEFT JOIN student_enrollments se ON sp.id = se.progress_id
                LEFT JOIN session_registrations sr ON se.session_registration_id = sr.id
                LEFT JOIN sessions s ON sr.session_id = s.id
                WHERE se.status = 'enrolled' 
                  AND sp.at_risk_of_dropout = true 
                  AND sp.risk_factors IS NOT NULL
            ";

            $params = [];
            if ($formationId) {
                $sql .= " AND s.formation_id = :formation_id";
                $params['formation_id'] = $formationId;
            }

            $sql .= " GROUP BY sp.risk_factors ORDER BY count DESC";

            $stmt = $this->entityManager->getConnection()->prepare($sql);
            $results = $stmt->executeQuery($params)->fetchAllAssociative();

            // Process JSON risk factors
            $riskFactorCounts = [];
            foreach ($results as $result) {
                try {
                    $factors = json_decode($result['risk_factors'], true) ?: [];
                    foreach ($factors as $factor) {
                        $riskFactorCounts[$factor] = ($riskFactorCounts[$factor] ?? 0) + $result['count'];
                    }
                } catch (\Exception $jsonError) {
                    $this->logger->warning('Invalid JSON in risk factors', [
                        'risk_factors' => $result['risk_factors'],
                        'error' => $jsonError->getMessage(),
                    ]);
                }
            }

            arsort($riskFactorCounts);

            $this->logger->debug('Risk factors analysis completed', [
                'formation_id' => $formationId,
                'total_factors' => count($riskFactorCounts),
                'raw_results' => count($results),
                'top_factor' => !empty($riskFactorCounts) ? array_key_first($riskFactorCounts) : null,
            ]);

            return $riskFactorCounts;
        } catch (\Exception $e) {
            $this->logger->error('Error analyzing risk factors', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'formation_id' => $formationId,
            ]);

            return [];
        }
    }

    /**
     * Get detailed progress breakdown for a student.
     */
    private function getProgressBreakdown($progress): array
    {
        try {
            $this->logger->debug('Calculating progress breakdown', [
                'progress_id' => $progress?->getId(),
                'completion_percentage' => $progress?->getCompletionPercentage(),
            ]);

            $breakdown = [
                'overall' => $progress->getCompletionPercentage(),
                'modules' => $progress->getModuleProgress() ?? [],
                'chapters' => $progress->getChapterProgress() ?? [],
                'last_activity' => $progress->getLastActivity(),
                'total_time_spent' => $progress->getTotalTimeSpent() ?? 0,
                'engagement_level' => $progress->getEngagementScore() ?? 0,
            ];

            $this->logger->debug('Progress breakdown calculated', [
                'overall_progress' => $breakdown['overall'],
                'modules_count' => count($breakdown['modules']),
                'chapters_count' => count($breakdown['chapters']),
                'total_time_spent' => $breakdown['total_time_spent'],
                'engagement_level' => $breakdown['engagement_level'],
            ]);

            return $breakdown;
        } catch (\Exception $e) {
            $this->logger->error('Error calculating progress breakdown', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'progress_id' => $progress?->getId(),
            ]);

            return [
                'overall' => 0,
                'modules' => [],
                'chapters' => [],
                'last_activity' => null,
                'total_time_spent' => 0,
                'engagement_level' => 0,
            ];
        }
    }

    /**
     * Get activity timeline for a student.
     */
    private function getActivityTimeline($enrollment): array
    {
        try {
            $this->logger->debug('Generating activity timeline', [
                'enrollment_id' => $enrollment->getId(),
                'student_id' => $enrollment->getStudent()?->getId(),
            ]);

            // This would typically come from an activity log table
            // For now, return a placeholder structure
            $timeline = [
                [
                    'date' => new \DateTime('-2 days'),
                    'type' => 'progress',
                    'description' => 'Chapitre terminé: Introduction aux concepts',
                    'progress_change' => 10,
                ],
                [
                    'date' => new \DateTime('-5 days'),
                    'type' => 'login',
                    'description' => 'Connexion à la plateforme',
                    'progress_change' => 0,
                ],
                // Add more timeline entries...
            ];

            $this->logger->debug('Activity timeline generated', [
                'enrollment_id' => $enrollment->getId(),
                'timeline_entries' => count($timeline),
            ]);

            return $timeline;
        } catch (\Exception $e) {
            $this->logger->error('Error generating activity timeline', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'enrollment_id' => $enrollment->getId(),
            ]);

            return [];
        }
    }

    /**
     * Get intervention history for a student.
     */
    private function getInterventionHistory($enrollment): array
    {
        try {
            $this->logger->debug('Retrieving intervention history', [
                'enrollment_id' => $enrollment->getId(),
            ]);

            // Extract interventions from admin notes for now
            $notes = $enrollment->getAdminNotes() ?? '';
            $interventions = [];
            
            // Parse intervention notes (simplified)
            if (preg_match_all('/\[([^\]]+)\] Intervention ([^:]+): (.+)/', $notes, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $interventions[] = [
                        'date' => $match[1],
                        'type' => trim($match[2]),
                        'notes' => trim($match[3]),
                    ];
                }
            }

            $this->logger->debug('Intervention history retrieved', [
                'enrollment_id' => $enrollment->getId(),
                'interventions_count' => count($interventions),
                'notes_length' => strlen($notes),
            ]);
            
            return $interventions;
        } catch (\Exception $e) {
            $this->logger->error('Error retrieving intervention history', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'enrollment_id' => $enrollment->getId(),
            ]);

            return [];
        }
    }

    /**
     * Get risk assessment details.
     */
    private function getRiskAssessment($progress): array
    {
        try {
            $this->logger->debug('Calculating risk assessment', [
                'progress_id' => $progress?->getId(),
                'at_risk' => $progress?->isAtRiskOfDropout(),
                'risk_score' => $progress?->getRiskScore(),
            ]);

            $lastActivity = $progress->getLastActivity();
            $daysSinceLastActivity = $lastActivity ? 
                (new \DateTime())->diff($lastActivity)->days : 999;

            $assessment = [
                'is_at_risk' => $progress->isAtRiskOfDropout(),
                'risk_score' => $progress->getRiskScore() ?? 0,
                'risk_factors' => [], // Placeholder
                'last_activity_days' => $daysSinceLastActivity,
                'engagement_trend' => $this->calculateEngagementTrend($progress),
            ];

            $this->logger->debug('Risk assessment calculated', [
                'progress_id' => $progress?->getId(),
                'is_at_risk' => $assessment['is_at_risk'],
                'risk_score' => $assessment['risk_score'],
                'last_activity_days' => $assessment['last_activity_days'],
                'engagement_trend' => $assessment['engagement_trend'],
            ]);

            return $assessment;
        } catch (\Exception $e) {
            $this->logger->error('Error calculating risk assessment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'progress_id' => $progress?->getId(),
            ]);

            return [
                'is_at_risk' => false,
                'risk_score' => 0,
                'risk_factors' => [],
                'last_activity_days' => 999,
                'engagement_trend' => 'unknown',
            ];
        }
    }

    /**
     * Calculate engagement trend.
     */
    private function calculateEngagementTrend($progress): string
    {
        try {
            $this->logger->debug('Calculating engagement trend', [
                'progress_id' => $progress?->getId(),
                'engagement_score' => $progress?->getEngagementScore(),
            ]);

            // Simplified trend calculation
            $currentEngagement = $progress->getEngagementScore() ?? 0;
            $threshold = 5; // Arbitrary threshold for stable engagement

            $trend = match (true) {
                $currentEngagement > $threshold + 2 => 'improving',
                $currentEngagement < $threshold - 2 => 'declining',
                default => 'stable'
            };

            $this->logger->debug('Engagement trend calculated', [
                'progress_id' => $progress?->getId(),
                'current_engagement' => $currentEngagement,
                'threshold' => $threshold,
                'trend' => $trend,
            ]);

            return $trend;
        } catch (\Exception $e) {
            $this->logger->error('Error calculating engagement trend', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'progress_id' => $progress?->getId(),
            ]);

            return 'unknown';
        }
    }

    /**
     * Get risk distribution for chart.
     */
    private function getRiskDistribution(?string $formationId): array
    {
        try {
            $this->logger->debug('Calculating risk distribution', ['formation_id' => $formationId]);

            $sql = "
                SELECT 
                    CASE 
                        WHEN sp.risk_score >= 70 THEN 'High'
                        WHEN sp.risk_score >= 40 THEN 'Medium'
                        ELSE 'Low'
                    END as risk_level,
                    COUNT(*) as count
                FROM student_progress sp
                LEFT JOIN student_enrollments se ON sp.id = se.progress_id
                LEFT JOIN session_registrations sr ON se.session_registration_id = sr.id
                LEFT JOIN sessions s ON sr.session_id = s.id
                WHERE se.status = 'enrolled'
            ";

            $params = [];
            if ($formationId) {
                $sql .= " AND s.formation_id = :formation_id";
                $params['formation_id'] = $formationId;
            }

            $sql .= " GROUP BY risk_level";

            $stmt = $this->entityManager->getConnection()->prepare($sql);
            $results = $stmt->executeQuery($params)->fetchAllAssociative();

            $this->logger->debug('Risk distribution calculated', [
                'formation_id' => $formationId,
                'distribution_levels' => count($results),
                'results' => $results,
            ]);

            return $results;
        } catch (\Exception $e) {
            $this->logger->error('Error calculating risk distribution', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'formation_id' => $formationId,
            ]);

            return [];
        }
    }

    /**
     * Get completion forecast.
     */
    private function getCompletionForecast(?string $formationId): array
    {
        try {
            $this->logger->debug('Calculating completion forecast', ['formation_id' => $formationId]);

            // Simplified forecast calculation
            $activeEnrollments = $this->enrollmentRepository->findEnrollmentsWithFilters([
                'status' => StudentEnrollment::STATUS_ENROLLED,
                'formation' => $formationId,
            ]);

            $forecast = [
                'total_active' => count($activeEnrollments),
                'expected_completions_30_days' => 0,
                'expected_dropouts_30_days' => 0,
                'completion_rate_trend' => 'stable',
            ];

            foreach ($activeEnrollments as $enrollment) {
                $progress = $enrollment->getProgress();
                if (!$progress) continue;

                if ($progress->getCompletionPercentage() > 80) {
                    $forecast['expected_completions_30_days']++;
                } elseif ($progress->isAtRiskOfDropout()) {
                    $forecast['expected_dropouts_30_days']++;
                }
            }

            $this->logger->debug('Completion forecast calculated', [
                'formation_id' => $formationId,
                'total_active' => $forecast['total_active'],
                'expected_completions' => $forecast['expected_completions_30_days'],
                'expected_dropouts' => $forecast['expected_dropouts_30_days'],
            ]);

            return $forecast;
        } catch (\Exception $e) {
            $this->logger->error('Error calculating completion forecast', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'formation_id' => $formationId,
            ]);

            return [
                'total_active' => 0,
                'expected_completions_30_days' => 0,
                'expected_dropouts_30_days' => 0,
                'completion_rate_trend' => 'unknown',
            ];
        }
    }

    /**
     * Get progress distribution chart data.
     */
    private function getProgressDistributionChart(?string $formationId): array
    {
        try {
            $this->logger->debug('Generating progress distribution chart data', ['formation_id' => $formationId]);

            $sql = "
                SELECT 
                    CASE 
                        WHEN sp.completion_percentage < 25 THEN '0-24%'
                        WHEN sp.completion_percentage < 50 THEN '25-49%'
                        WHEN sp.completion_percentage < 75 THEN '50-74%'
                        ELSE '75-100%'
                    END as range,
                    COUNT(*) as count
                FROM student_progress sp
                LEFT JOIN student_enrollments se ON sp.id = se.progress_id
                LEFT JOIN session_registrations sr ON se.session_registration_id = sr.id
                LEFT JOIN sessions s ON sr.session_id = s.id
                WHERE se.status = 'enrolled'
            ";

            $params = [];
            if ($formationId) {
                $sql .= " AND s.formation_id = :formation_id";
                $params['formation_id'] = $formationId;
            }

            $sql .= " GROUP BY range ORDER BY MIN(sp.completion_percentage)";

            $stmt = $this->entityManager->getConnection()->prepare($sql);
            $results = $stmt->executeQuery($params)->fetchAllAssociative();

            $this->logger->debug('Progress distribution chart data generated', [
                'formation_id' => $formationId,
                'data_points' => count($results),
                'results' => $results,
            ]);

            return $results;
        } catch (\Exception $e) {
            $this->logger->error('Error generating progress distribution chart data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'formation_id' => $formationId,
            ]);

            return [];
        }
    }

    /**
     * Get engagement levels chart data.
     */
    private function getEngagementLevelsChart(?string $formationId): array
    {
        try {
            $this->logger->debug('Generating engagement levels chart data', ['formation_id' => $formationId]);

            $sql = "
                SELECT 
                    sp.engagement_level,
                    COUNT(*) as count
                FROM student_progress sp
                LEFT JOIN student_enrollments se ON sp.id = se.progress_id
                LEFT JOIN session_registrations sr ON se.session_registration_id = sr.id
                LEFT JOIN sessions s ON sr.session_id = s.id
                WHERE se.status = 'enrolled'
            ";

            $params = [];
            if ($formationId) {
                $sql .= " AND s.formation_id = :formation_id";
                $params['formation_id'] = $formationId;
            }

            $sql .= " GROUP BY sp.engagement_level ORDER BY sp.engagement_level";

            $stmt = $this->entityManager->getConnection()->prepare($sql);
            $results = $stmt->executeQuery($params)->fetchAllAssociative();

            $this->logger->debug('Engagement levels chart data generated', [
                'formation_id' => $formationId,
                'engagement_levels' => count($results),
                'results' => $results,
            ]);

            return $results;
        } catch (\Exception $e) {
            $this->logger->error('Error generating engagement levels chart data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'formation_id' => $formationId,
            ]);

            return [];
        }
    }

    /**
     * Get risk factors chart data.
     */
    private function getRiskFactorsChart(?string $formationId): array
    {
        try {
            $this->logger->debug('Generating risk factors chart data', ['formation_id' => $formationId]);

            $riskFactors = $this->getRiskFactorsAnalysis($formationId);

            $this->logger->debug('Risk factors chart data generated', [
                'formation_id' => $formationId,
                'factors_count' => count($riskFactors),
                'top_factors' => array_slice(array_keys($riskFactors), 0, 5),
            ]);

            return $riskFactors;
        } catch (\Exception $e) {
            $this->logger->error('Error generating risk factors chart data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'formation_id' => $formationId,
            ]);

            return [];
        }
    }

    /**
     * Get completion trends chart data.
     */
    private function getCompletionTrendsChart(?string $formationId): array
    {
        try {
            $this->logger->debug('Generating completion trends chart data', ['formation_id' => $formationId]);

            $trends = $this->getProgressTrends($formationId);

            $this->logger->debug('Completion trends chart data generated', [
                'formation_id' => $formationId,
                'data_points' => count($trends),
                'date_range' => !empty($trends) ? [
                    'from' => end($trends)['date'] ?? null,
                    'to' => $trends[0]['date'] ?? null,
                ] : null,
            ]);

            return $trends;
        } catch (\Exception $e) {
            $this->logger->error('Error generating completion trends chart data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'formation_id' => $formationId,
            ]);

            return [];
        }
    }
}
