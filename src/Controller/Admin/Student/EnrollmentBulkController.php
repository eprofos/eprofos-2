<?php

declare(strict_types=1);

namespace App\Controller\Admin\Student;

use App\Entity\Core\StudentEnrollment;
use App\Entity\Training\Formation;
use App\Entity\User\Student;
use App\Repository\Core\StudentEnrollmentRepository;
use App\Repository\Training\SessionRepository;
use App\Repository\User\StudentRepository;
use App\Service\Student\StudentEnrollmentService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * EnrollmentBulkController handles bulk enrollment operations.
 *
 * Provides bulk enrollment, status changes, export, and unenrollment
 * capabilities with comprehensive error handling and progress tracking.
 */
#[Route('/admin/student/enrollment/bulk')]
#[IsGranted('ROLE_ADMIN')]
class EnrollmentBulkController extends AbstractController
{
    public function __construct(
        private StudentEnrollmentService $enrollmentService,
        private EntityManagerInterface $entityManager,
        private StudentEnrollmentRepository $enrollmentRepository,
        private SessionRepository $sessionRepository,
        private StudentRepository $studentRepository,
        private ValidatorInterface $validator,
        private LoggerInterface $logger,
    ) {}

    /**
     * Bulk enrollment interface.
     */
    #[Route('/enroll', name: 'admin_enrollment_bulk_enroll', methods: ['GET', 'POST'])]
    public function bulkEnroll(Request $request): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $this->logger->info('Bulk enrollment interface accessed', [
            'user_id' => $userId,
            'method' => $request->getMethod(),
            'ip' => $request->getClientIp(),
        ]);

        if ($request->isMethod('POST')) {
            try {
                $this->logger->info('Starting bulk enrollment process', [
                    'user_id' => $userId,
                    'request_data' => [
                        'session_id' => $request->request->get('session_id'),
                        'enrollment_method' => $request->request->get('enrollment_method'),
                        'notify_students' => $request->request->getBoolean('notify_students', false),
                    ],
                ]);

                $sessionId = $request->request->get('session_id');
                $enrollmentMethod = $request->request->get('enrollment_method');
                $notifyStudents = $request->request->getBoolean('notify_students', false);
                $adminNotes = $request->request->get('admin_notes', '');

                if (!$sessionId) {
                    $this->logger->error('Bulk enrollment failed: No session selected', [
                        'user_id' => $userId,
                    ]);

                    throw new Exception('Une session doit être sélectionnée');
                }

                $session = $this->sessionRepository->find($sessionId);
                if (!$session) {
                    $this->logger->error('Bulk enrollment failed: Session not found', [
                        'user_id' => $userId,
                        'session_id' => $sessionId,
                    ]);

                    throw new Exception('Session non trouvée');
                }

                $this->logger->info('Session found for bulk enrollment', [
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                    'session_name' => $session->getName(),
                    'formation_id' => $session->getFormation()?->getId(),
                ]);

                $students = [];

                if ($enrollmentMethod === 'manual_selection') {
                    $this->logger->info('Processing manual selection enrollment method', [
                        'user_id' => $userId,
                        'session_id' => $sessionId,
                    ]);

                    $studentIds = $request->request->all('student_ids');
                    if (empty($studentIds)) {
                        $this->logger->error('Bulk enrollment failed: No students selected for manual selection', [
                            'user_id' => $userId,
                            'session_id' => $sessionId,
                        ]);

                        throw new Exception('Aucun étudiant sélectionné');
                    }

                    $students = $this->studentRepository->findBy(['id' => $studentIds]);
                    $this->logger->info('Students found for manual selection', [
                        'user_id' => $userId,
                        'session_id' => $sessionId,
                        'requested_student_ids' => $studentIds,
                        'found_students_count' => count($students),
                    ]);
                } elseif ($enrollmentMethod === 'csv_upload') {
                    $this->logger->info('Processing CSV upload enrollment method', [
                        'user_id' => $userId,
                        'session_id' => $sessionId,
                    ]);

                    $csvFile = $request->files->get('csv_file');
                    if (!$csvFile) {
                        $this->logger->error('Bulk enrollment failed: No CSV file provided', [
                            'user_id' => $userId,
                            'session_id' => $sessionId,
                        ]);

                        throw new Exception('Fichier CSV requis');
                    }

                    try {
                        $students = $this->processCSVFile($csvFile);
                        $this->logger->info('CSV file processed successfully', [
                            'user_id' => $userId,
                            'session_id' => $sessionId,
                            'csv_filename' => $csvFile->getClientOriginalName(),
                            'csv_size' => $csvFile->getSize(),
                            'students_found' => count($students),
                        ]);
                    } catch (Exception $e) {
                        $this->logger->error('CSV processing failed', [
                            'user_id' => $userId,
                            'session_id' => $sessionId,
                            'csv_filename' => $csvFile->getClientOriginalName(),
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);

                        throw new Exception('Erreur lors du traitement du fichier CSV : ' . $e->getMessage());
                    }
                } elseif ($enrollmentMethod === 'criteria_based') {
                    $this->logger->info('Processing criteria-based enrollment method', [
                        'user_id' => $userId,
                        'session_id' => $sessionId,
                    ]);

                    try {
                        $criteria = $this->buildCriteriaFromRequest($request);
                        $this->logger->info('Criteria built from request', [
                            'user_id' => $userId,
                            'session_id' => $sessionId,
                            'criteria' => $criteria,
                        ]);

                        $students = $this->studentRepository->findByCriteria($criteria);
                        $this->logger->info('Students found by criteria', [
                            'user_id' => $userId,
                            'session_id' => $sessionId,
                            'criteria' => $criteria,
                            'students_found' => count($students),
                        ]);
                    } catch (Exception $e) {
                        $this->logger->error('Criteria-based search failed', [
                            'user_id' => $userId,
                            'session_id' => $sessionId,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);

                        throw new Exception('Erreur lors de la recherche par critères : ' . $e->getMessage());
                    }
                }

                if (empty($students)) {
                    $this->logger->warning('No valid students found for bulk enrollment', [
                        'user_id' => $userId,
                        'session_id' => $sessionId,
                        'enrollment_method' => $enrollmentMethod,
                    ]);

                    throw new Exception('Aucun étudiant valide trouvé');
                }

                $this->logger->info('Starting bulk enrollment service call', [
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                    'students_count' => count($students),
                    'notify_students' => $notifyStudents,
                    'admin_notes_length' => strlen($adminNotes),
                ]);

                try {
                    $results = $this->enrollmentService->bulkEnrollStudents(
                        $session,
                        $students,
                        $adminNotes,
                        $notifyStudents,
                    );

                    $this->logger->info('Bulk enrollment completed successfully', [
                        'user_id' => $userId,
                        'session_id' => $sessionId,
                        'total_students' => count($students),
                        'successful_enrollments' => $results['successful'] ?? 0,
                        'failed_enrollments' => $results['failed'] ?? 0,
                        'skipped_enrollments' => $results['skipped'] ?? 0,
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Bulk enrollment service failed', [
                        'user_id' => $userId,
                        'session_id' => $sessionId,
                        'students_count' => count($students),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    throw new Exception('Erreur lors de l\'inscription en lot : ' . $e->getMessage());
                }

                return $this->render('admin/student/enrollment/bulk_enroll_results.html.twig', [
                    'results' => $results,
                    'session' => $session,
                    'notifyStudents' => $notifyStudents,
                ]);
            } catch (Exception $e) {
                $this->logger->error('Bulk enrollment process failed with exception', [
                    'user_id' => $userId,
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->addFlash('error', 'Erreur lors de l\'inscription en lot : ' . $e->getMessage());
            }
        }

        try {
            // Get available sessions
            $sessions = $this->sessionRepository->createQueryBuilder('s')
                ->leftJoin('s.formation', 'f')
                ->where('s.startDate > :now OR s.endDate IS NULL')
                ->setParameter('now', new DateTime())
                ->orderBy('s.startDate', 'ASC')
                ->getQuery()
                ->getResult()
            ;

            $this->logger->info('Available sessions loaded for bulk enrollment interface', [
                'user_id' => $userId,
                'sessions_count' => count($sessions),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to load available sessions', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $sessions = [];
            $this->addFlash('error', 'Erreur lors du chargement des sessions disponibles');
        }

        return $this->render('admin/student/enrollment/bulk_enroll.html.twig', [
            'sessions' => $sessions,
        ]);
    }

    /**
     * Bulk status change interface.
     */
    #[Route('/status-change', name: 'admin_enrollment_bulk_status_change', methods: ['GET', 'POST'])]
    public function bulkStatusChange(Request $request): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $this->logger->info('Bulk status change interface accessed', [
            'user_id' => $userId,
            'method' => $request->getMethod(),
            'ip' => $request->getClientIp(),
        ]);

        if ($request->isMethod('POST')) {
            try {
                $this->logger->info('Starting bulk status change process', [
                    'user_id' => $userId,
                    'request_data' => [
                        'enrollment_ids_count' => count($request->request->all('enrollment_ids')),
                        'new_status' => $request->request->get('new_status'),
                        'notify_students' => $request->request->getBoolean('notify_students', false),
                    ],
                ]);

                $enrollmentIds = $request->request->all('enrollment_ids');
                $newStatus = $request->request->get('new_status');
                $dropoutReason = $request->request->get('dropout_reason', '');
                $adminNotes = $request->request->get('admin_notes', '');
                $notifyStudents = $request->request->getBoolean('notify_students', false);

                if (empty($enrollmentIds) || !$newStatus) {
                    $this->logger->error('Bulk status change failed: Missing required fields', [
                        'user_id' => $userId,
                        'enrollment_ids_empty' => empty($enrollmentIds),
                        'new_status_empty' => !$newStatus,
                    ]);

                    throw new Exception('Inscriptions et nouveau statut requis');
                }

                if (!in_array($newStatus, array_keys(StudentEnrollment::STATUSES), true)) {
                    $this->logger->error('Bulk status change failed: Invalid status', [
                        'user_id' => $userId,
                        'invalid_status' => $newStatus,
                        'valid_statuses' => array_keys(StudentEnrollment::STATUSES),
                    ]);

                    throw new Exception('Statut invalide');
                }

                try {
                    $enrollments = $this->enrollmentRepository->findBy(['id' => $enrollmentIds]);
                    $this->logger->info('Enrollments found for status change', [
                        'user_id' => $userId,
                        'requested_ids' => $enrollmentIds,
                        'found_count' => count($enrollments),
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Failed to fetch enrollments for status change', [
                        'user_id' => $userId,
                        'enrollment_ids' => $enrollmentIds,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    throw new Exception('Erreur lors de la récupération des inscriptions : ' . $e->getMessage());
                }

                if (empty($enrollments)) {
                    $this->logger->warning('No valid enrollments found for status change', [
                        'user_id' => $userId,
                        'requested_ids' => $enrollmentIds,
                    ]);

                    throw new Exception('Aucune inscription valide trouvée');
                }

                $this->logger->info('Starting bulk status update service call', [
                    'user_id' => $userId,
                    'enrollments_count' => count($enrollments),
                    'new_status' => $newStatus,
                    'dropout_reason_length' => strlen($dropoutReason),
                    'admin_notes_length' => strlen($adminNotes),
                    'notify_students' => $notifyStudents,
                ]);

                try {
                    $results = $this->enrollmentService->bulkUpdateStatus(
                        $enrollments,
                        $newStatus,
                        $dropoutReason,
                        $adminNotes,
                        $notifyStudents,
                    );

                    $this->logger->info('Bulk status change completed successfully', [
                        'user_id' => $userId,
                        'enrollments_count' => count($enrollments),
                        'new_status' => $newStatus,
                        'successful_updates' => $results['successful'] ?? 0,
                        'failed_updates' => $results['failed'] ?? 0,
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Bulk status update service failed', [
                        'user_id' => $userId,
                        'enrollments_count' => count($enrollments),
                        'new_status' => $newStatus,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    throw new Exception('Erreur lors de la mise à jour des statuts : ' . $e->getMessage());
                }

                return $this->render('admin/student/enrollment/bulk_status_results.html.twig', [
                    'results' => $results,
                    'newStatus' => $newStatus,
                    'statusLabel' => StudentEnrollment::STATUSES[$newStatus],
                ]);
            } catch (Exception $e) {
                $this->logger->error('Bulk status change process failed with exception', [
                    'user_id' => $userId,
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->addFlash('error', 'Erreur lors du changement de statut en lot : ' . $e->getMessage());
            }
        }

        try {
            // Get enrollments with filtering
            $filters = $this->buildFiltersFromRequest($request);
            $this->logger->info('Filters built for status change interface', [
                'user_id' => $userId,
                'filters' => $filters,
            ]);

            $enrollments = $this->enrollmentRepository->findEnrollmentsWithFilters($filters);
            $this->logger->info('Enrollments loaded for status change interface', [
                'user_id' => $userId,
                'filters' => $filters,
                'enrollments_count' => count($enrollments),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to load enrollments for status change interface', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $enrollments = [];
            $this->addFlash('error', 'Erreur lors du chargement des inscriptions');
        }

        return $this->render('admin/student/enrollment/bulk_status_change.html.twig', [
            'enrollments' => $enrollments,
            'statuses' => StudentEnrollment::STATUSES,
            'filters' => $filters ?? [],
        ]);
    }

    /**
     * Bulk export interface.
     */
    #[Route('/export', name: 'admin_enrollment_bulk_export', methods: ['GET', 'POST'])]
    public function bulkExport(Request $request): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $this->logger->info('Bulk export interface accessed', [
            'user_id' => $userId,
            'method' => $request->getMethod(),
            'ip' => $request->getClientIp(),
        ]);

        if ($request->isMethod('POST')) {
            // Handle AJAX preview requests
            if ($request->isXmlHttpRequest()) {
                try {
                    $this->logger->info('Processing AJAX preview request for export', [
                        'user_id' => $userId,
                    ]);

                    $filters = $this->buildFiltersFromRequest($request);
                    $this->logger->info('Filters built for export preview', [
                        'user_id' => $userId,
                        'filters' => $filters,
                    ]);

                    $enrollmentsCount = $this->enrollmentRepository->countEnrollmentsWithFilters($filters);
                    $this->logger->info('Export preview count calculated', [
                        'user_id' => $userId,
                        'filters' => $filters,
                        'count' => $enrollmentsCount,
                    ]);

                    return new JsonResponse([
                        'success' => true,
                        'count' => $enrollmentsCount,
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Export preview failed', [
                        'user_id' => $userId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    return new JsonResponse([
                        'success' => false,
                        'error' => 'Erreur lors du calcul du nombre d\'inscriptions',
                    ], 500);
                }
            }

            // Handle actual export requests
            try {
                $format = $request->request->get('format', 'csv');
                $filters = $this->buildFiltersFromRequest($request);
                $includeProgress = $request->request->getBoolean('include_progress', false);
                $includeAttendance = $request->request->getBoolean('include_attendance', false);

                $this->logger->info('Starting bulk export process', [
                    'user_id' => $userId,
                    'format' => $format,
                    'filters' => $filters,
                    'include_progress' => $includeProgress,
                    'include_attendance' => $includeAttendance,
                ]);

                return $this->generateExport($format, $filters, $includeProgress, $includeAttendance);
            } catch (Exception $e) {
                $this->logger->error('Bulk export failed', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->addFlash('error', 'Erreur lors de l\'export : ' . $e->getMessage());
            }
        }

        try {
            // Get current filters for preview
            $filters = $this->buildFiltersFromRequest($request);
            $enrollmentsCount = $this->enrollmentRepository->countEnrollmentsWithFilters($filters);

            $this->logger->info('Export interface loaded with preview count', [
                'user_id' => $userId,
                'filters' => $filters,
                'count' => $enrollmentsCount,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to calculate enrollments count for export preview', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $enrollmentsCount = 0;
            $this->addFlash('warning', 'Impossible de calculer le nombre d\'inscriptions à exporter');
        }

        try {
            // Get formations for the dropdown
            $formations = $this->entityManager->getRepository(Formation::class)
                ->createQueryBuilder('f')
                ->select('f.id, f.title')
                ->where('f.isActive = :active')
                ->setParameter('active', true)
                ->orderBy('f.title', 'ASC')
                ->getQuery()
                ->getArrayResult()
            ;

            $this->logger->info('Formations loaded for export interface', [
                'user_id' => $userId,
                'formations_count' => count($formations),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to load formations for export interface', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $formations = [];
            $this->addFlash('warning', 'Impossible de charger les formations');
        }

        try {
            // Get sessions for the dropdown
            $sessions = $this->sessionRepository->createQueryBuilder('s')
                ->select('s.id, s.name, f.id as formation_id, f.title as formationTitle')
                ->leftJoin('s.formation', 'f')
                ->where('s.isActive = :active')
                ->setParameter('active', true)
                ->orderBy('f.title, s.name', 'ASC')
                ->getQuery()
                ->getArrayResult()
            ;

            $this->logger->info('Sessions loaded for export interface', [
                'user_id' => $userId,
                'sessions_count' => count($sessions),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to load sessions for export interface', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $sessions = [];
            $this->addFlash('warning', 'Impossible de charger les sessions');
        }

        return $this->render('admin/student/enrollment/bulk_export.html.twig', [
            'enrollmentsCount' => $enrollmentsCount,
            'filters' => $filters ?? [],
            'formations' => $formations,
            'sessions' => $sessions,
        ]);
    }

    /**
     * Bulk unenrollment interface.
     */
    #[Route('/unenroll', name: 'admin_enrollment_bulk_unenroll', methods: ['GET', 'POST'])]
    public function bulkUnenroll(Request $request): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $this->logger->info('Bulk unenrollment interface accessed', [
            'user_id' => $userId,
            'method' => $request->getMethod(),
            'ip' => $request->getClientIp(),
        ]);

        if ($request->isMethod('POST')) {
            try {
                $this->logger->info('Starting bulk unenrollment process', [
                    'user_id' => $userId,
                    'request_data' => [
                        'enrollment_ids_count' => count($request->request->all('enrollment_ids')),
                        'notify_students' => $request->request->getBoolean('notify_students', false),
                    ],
                ]);

                $enrollmentIds = $request->request->all('enrollment_ids');
                $reason = $request->request->get('unenroll_reason', 'Admin bulk unenrollment');
                $notifyStudents = $request->request->getBoolean('notify_students', false);

                if (empty($enrollmentIds)) {
                    $this->logger->error('Bulk unenrollment failed: No enrollments selected', [
                        'user_id' => $userId,
                    ]);

                    throw new Exception('Aucune inscription sélectionnée');
                }

                try {
                    $enrollments = $this->enrollmentRepository->findBy(['id' => $enrollmentIds]);
                    $this->logger->info('Enrollments found for unenrollment', [
                        'user_id' => $userId,
                        'requested_ids' => $enrollmentIds,
                        'found_count' => count($enrollments),
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Failed to fetch enrollments for unenrollment', [
                        'user_id' => $userId,
                        'enrollment_ids' => $enrollmentIds,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    throw new Exception('Erreur lors de la récupération des inscriptions : ' . $e->getMessage());
                }

                if (empty($enrollments)) {
                    $this->logger->warning('No valid enrollments found for unenrollment', [
                        'user_id' => $userId,
                        'requested_ids' => $enrollmentIds,
                    ]);

                    throw new Exception('Aucune inscription valide trouvée');
                }

                $this->logger->info('Starting bulk unenrollment service call', [
                    'user_id' => $userId,
                    'enrollments_count' => count($enrollments),
                    'reason_length' => strlen($reason),
                    'notify_students' => $notifyStudents,
                ]);

                try {
                    $results = $this->enrollmentService->bulkUnenroll(
                        $enrollments,
                        $reason,
                        $notifyStudents,
                    );

                    $this->logger->info('Bulk unenrollment completed successfully', [
                        'user_id' => $userId,
                        'enrollments_count' => count($enrollments),
                        'successful_unenrollments' => $results['successful'] ?? 0,
                        'failed_unenrollments' => $results['failed'] ?? 0,
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Bulk unenrollment service failed', [
                        'user_id' => $userId,
                        'enrollments_count' => count($enrollments),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    throw new Exception('Erreur lors de la désinscription : ' . $e->getMessage());
                }

                return $this->render('admin/student/enrollment/bulk_unenroll_results.html.twig', [
                    'results' => $results,
                    'reason' => $reason,
                ]);
            } catch (Exception $e) {
                $this->logger->error('Bulk unenrollment process failed with exception', [
                    'user_id' => $userId,
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->addFlash('error', 'Erreur lors de la désinscription en lot : ' . $e->getMessage());
            }
        }

        try {
            // Get active enrollments only
            $filters = array_merge($this->buildFiltersFromRequest($request), ['status' => StudentEnrollment::STATUS_ENROLLED]);
            $this->logger->info('Filters built for unenrollment interface', [
                'user_id' => $userId,
                'filters' => $filters,
            ]);

            $enrollments = $this->enrollmentRepository->findEnrollmentsWithFilters($filters);
            $this->logger->info('Active enrollments loaded for unenrollment interface', [
                'user_id' => $userId,
                'filters' => $filters,
                'enrollments_count' => count($enrollments),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to load enrollments for unenrollment interface', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $enrollments = [];
            $this->addFlash('error', 'Erreur lors du chargement des inscriptions');
        }

        return $this->render('admin/student/enrollment/bulk_unenroll.html.twig', [
            'enrollments' => $enrollments,
            'filters' => $filters ?? [],
        ]);
    }

    /**
     * AJAX endpoint for student search with criteria.
     */
    #[Route('/search-students', name: 'admin_enrollment_bulk_search_students', methods: ['POST'])]
    public function searchStudents(Request $request): JsonResponse
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $this->logger->info('Student search AJAX endpoint accessed', [
            'user_id' => $userId,
            'ip' => $request->getClientIp(),
        ]);

        try {
            $this->logger->info('Starting student search by criteria', [
                'user_id' => $userId,
            ]);

            $criteria = $this->buildCriteriaFromRequest($request);
            $this->logger->info('Criteria built for student search', [
                'user_id' => $userId,
                'criteria' => $criteria,
            ]);

            $students = $this->studentRepository->findByCriteria($criteria, 100); // Limit to 100 for performance
            $this->logger->info('Students found by criteria search', [
                'user_id' => $userId,
                'criteria' => $criteria,
                'students_found' => count($students),
            ]);

            $results = [];
            foreach ($students as $student) {
                $results[] = [
                    'id' => $student->getId(),
                    'name' => $student->getFullName(),
                    'email' => $student->getEmail(),
                    'education' => $student->getEducation(),
                    'profession' => $student->getProfession(),
                    'location' => $student->getLocation(),
                ];
            }

            $this->logger->info('Student search completed successfully', [
                'user_id' => $userId,
                'results_count' => count($results),
            ]);

            return new JsonResponse([
                'success' => true,
                'students' => $results,
                'count' => count($results),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Student search failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * AJAX endpoint for enrollment validation preview.
     */
    #[Route('/validate-enrollments', name: 'admin_enrollment_bulk_validate', methods: ['POST'])]
    public function validateEnrollments(Request $request): JsonResponse
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $this->logger->info('Enrollment validation AJAX endpoint accessed', [
            'user_id' => $userId,
            'ip' => $request->getClientIp(),
        ]);

        try {
            $this->logger->info('Starting enrollment validation', [
                'user_id' => $userId,
                'request_data' => [
                    'session_id' => $request->request->get('session_id'),
                    'student_ids_count' => count($request->request->all('student_ids')),
                ],
            ]);

            $sessionId = $request->request->get('session_id');
            $studentIds = $request->request->all('student_ids');

            if (!$sessionId || empty($studentIds)) {
                $this->logger->error('Enrollment validation failed: Missing required parameters', [
                    'user_id' => $userId,
                    'session_id_missing' => !$sessionId,
                    'student_ids_empty' => empty($studentIds),
                ]);

                throw new Exception('Session et étudiants requis');
            }

            try {
                $session = $this->sessionRepository->find($sessionId);
                if (!$session) {
                    $this->logger->error('Enrollment validation failed: Session not found', [
                        'user_id' => $userId,
                        'session_id' => $sessionId,
                    ]);

                    throw new Exception('Session non trouvée');
                }

                $students = $this->studentRepository->findBy(['id' => $studentIds]);
                $this->logger->info('Data loaded for enrollment validation', [
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                    'session_name' => $session->getName(),
                    'requested_students' => count($studentIds),
                    'found_students' => count($students),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to load data for enrollment validation', [
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                    'student_ids' => $studentIds,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new Exception('Erreur lors du chargement des données : ' . $e->getMessage());
            }

            try {
                $validation = $this->enrollmentService->validateBulkEnrollment($session, $students);
                $this->logger->info('Enrollment validation completed successfully', [
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                    'students_count' => count($students),
                    'validation_result' => [
                        'valid_count' => $validation['valid'] ?? 0,
                        'invalid_count' => $validation['invalid'] ?? 0,
                        'duplicate_count' => $validation['duplicates'] ?? 0,
                    ],
                ]);
            } catch (Exception $e) {
                $this->logger->error('Enrollment validation service failed', [
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                    'students_count' => count($students),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw new Exception('Erreur lors de la validation : ' . $e->getMessage());
            }

            return new JsonResponse([
                'success' => true,
                'validation' => $validation,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Enrollment validation failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Process CSV file for bulk enrollment.
     *
     * @param mixed $csvFile
     */
    private function processCSVFile($csvFile): array
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $this->logger->info('Starting CSV file processing', [
            'user_id' => $userId,
            'filename' => $csvFile->getClientOriginalName(),
            'file_size' => $csvFile->getSize(),
            'mime_type' => $csvFile->getMimeType(),
        ]);

        $students = [];

        try {
            $handle = fopen($csvFile->getPathname(), 'r');

            if (!$handle) {
                $this->logger->error('Failed to open CSV file for reading', [
                    'user_id' => $userId,
                    'filename' => $csvFile->getClientOriginalName(),
                    'pathname' => $csvFile->getPathname(),
                ]);

                throw new Exception('Impossible de lire le fichier CSV');
            }

            $this->logger->info('CSV file opened successfully', [
                'user_id' => $userId,
                'filename' => $csvFile->getClientOriginalName(),
            ]);

            $header = fgetcsv($handle); // Skip header row
            $expectedHeaders = ['email', 'first_name', 'last_name'];
            $rowNumber = 1; // Start from 1 since we skipped header
            $processedRows = 0;
            $validEmails = 0;
            $invalidEmails = 0;
            $foundStudents = 0;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                $processedRows++;

                if (count($row) < 3) {
                    $this->logger->warning('CSV row has insufficient columns', [
                        'user_id' => $userId,
                        'row_number' => $rowNumber,
                        'columns_count' => count($row),
                        'expected_minimum' => 3,
                    ]);

                    continue; // Skip invalid rows
                }

                $email = trim($row[0]);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $invalidEmails++;
                    $this->logger->warning('Invalid email found in CSV', [
                        'user_id' => $userId,
                        'row_number' => $rowNumber,
                        'invalid_email' => $email,
                    ]);

                    continue; // Skip invalid emails
                }

                $validEmails++;

                try {
                    $student = $this->studentRepository->findOneBy(['email' => $email]);
                    if ($student) {
                        $foundStudents++;
                        $students[] = $student;
                        $this->logger->debug('Student found for CSV email', [
                            'user_id' => $userId,
                            'row_number' => $rowNumber,
                            'email' => $email,
                            'student_id' => $student->getId(),
                        ]);
                    } else {
                        $this->logger->debug('No student found for CSV email', [
                            'user_id' => $userId,
                            'row_number' => $rowNumber,
                            'email' => $email,
                        ]);
                    }
                } catch (Exception $e) {
                    $this->logger->error('Error while searching for student', [
                        'user_id' => $userId,
                        'row_number' => $rowNumber,
                        'email' => $email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            fclose($handle);

            $this->logger->info('CSV file processing completed', [
                'user_id' => $userId,
                'filename' => $csvFile->getClientOriginalName(),
                'total_rows_processed' => $processedRows,
                'valid_emails' => $validEmails,
                'invalid_emails' => $invalidEmails,
                'students_found' => $foundStudents,
                'final_students_count' => count($students),
            ]);
        } catch (Exception $e) {
            $this->logger->error('CSV file processing failed', [
                'user_id' => $userId,
                'filename' => $csvFile->getClientOriginalName(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if (isset($handle) && $handle) {
                fclose($handle);
            }

            throw $e;
        }

        return $students;
    }

    /**
     * Build criteria array from request parameters.
     */
    private function buildCriteriaFromRequest(Request $request): array
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $this->logger->debug('Building criteria from request parameters', [
            'user_id' => $userId,
        ]);

        $criteria = [];

        if ($education = $request->get('education')) {
            $criteria['education'] = $education;
            $this->logger->debug('Added education criteria', [
                'user_id' => $userId,
                'education' => $education,
            ]);
        }

        if ($profession = $request->get('profession')) {
            $criteria['profession'] = $profession;
            $this->logger->debug('Added profession criteria', [
                'user_id' => $userId,
                'profession' => $profession,
            ]);
        }

        if ($location = $request->get('location')) {
            $criteria['location'] = $location;
            $this->logger->debug('Added location criteria', [
                'user_id' => $userId,
                'location' => $location,
            ]);
        }

        if ($ageMin = $request->get('age_min')) {
            $criteria['age_min'] = (int) $ageMin;
            $this->logger->debug('Added minimum age criteria', [
                'user_id' => $userId,
                'age_min' => (int) $ageMin,
            ]);
        }

        if ($ageMax = $request->get('age_max')) {
            $criteria['age_max'] = (int) $ageMax;
            $this->logger->debug('Added maximum age criteria', [
                'user_id' => $userId,
                'age_max' => (int) $ageMax,
            ]);
        }

        if ($hasCompletedFormations = $request->get('has_completed_formations')) {
            $criteria['has_completed_formations'] = $hasCompletedFormations === 'true';
            $this->logger->debug('Added completed formations criteria', [
                'user_id' => $userId,
                'has_completed_formations' => $hasCompletedFormations === 'true',
            ]);
        }

        $this->logger->info('Criteria built successfully from request', [
            'user_id' => $userId,
            'criteria_count' => count($criteria),
            'criteria' => $criteria,
        ]);

        return $criteria;
    }

    /**
     * Build filters array from request parameters.
     */
    private function buildFiltersFromRequest(Request $request): array
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $this->logger->debug('Building filters from request parameters', [
            'user_id' => $userId,
        ]);

        $filters = [];

        if ($status = $request->get('status')) {
            $filters['status'] = $status;
            $this->logger->debug('Added status filter', [
                'user_id' => $userId,
                'status' => $status,
            ]);
        }

        if ($formation = $request->get('formation')) {
            $filters['formation'] = $formation;
            $this->logger->debug('Added formation filter', [
                'user_id' => $userId,
                'formation' => $formation,
            ]);
        }

        if ($session = $request->get('session')) {
            $filters['session'] = $session;
            $this->logger->debug('Added session filter', [
                'user_id' => $userId,
                'session' => $session,
            ]);
        }

        if ($enrollmentSource = $request->get('enrollment_source')) {
            $filters['enrollment_source'] = $enrollmentSource;
            $this->logger->debug('Added enrollment source filter', [
                'user_id' => $userId,
                'enrollment_source' => $enrollmentSource,
            ]);
        }

        if ($enrolledAfter = $request->get('enrolled_after')) {
            try {
                $filters['enrolled_after'] = new DateTime($enrolledAfter);
                $this->logger->debug('Added enrolled after filter', [
                    'user_id' => $userId,
                    'enrolled_after' => $enrolledAfter,
                ]);
            } catch (Exception $e) {
                $this->logger->warning('Invalid enrolled_after date format', [
                    'user_id' => $userId,
                    'enrolled_after' => $enrolledAfter,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($enrolledBefore = $request->get('enrolled_before')) {
            try {
                $filters['enrolled_before'] = new DateTime($enrolledBefore);
                $this->logger->debug('Added enrolled before filter', [
                    'user_id' => $userId,
                    'enrolled_before' => $enrolledBefore,
                ]);
            } catch (Exception $e) {
                $this->logger->warning('Invalid enrolled_before date format', [
                    'user_id' => $userId,
                    'enrolled_before' => $enrolledBefore,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($studentSearch = $request->get('student_search')) {
            $filters['student_search'] = $studentSearch;
            $this->logger->debug('Added student search filter', [
                'user_id' => $userId,
                'student_search_length' => strlen($studentSearch),
            ]);
        }

        $this->logger->info('Filters built successfully from request', [
            'user_id' => $userId,
            'filters_count' => count($filters),
            'filters' => array_map(static fn ($value) => $value instanceof DateTime ? $value->format('Y-m-d H:i:s') : $value, $filters),
        ]);

        return $filters;
    }

    /**
     * Generate export file.
     */
    private function generateExport(string $format, array $filters, bool $includeProgress, bool $includeAttendance): StreamedResponse
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $this->logger->info('Starting export file generation', [
            'user_id' => $userId,
            'format' => $format,
            'filters' => array_map(static fn ($value) => $value instanceof DateTime ? $value->format('Y-m-d H:i:s') : $value, $filters),
            'include_progress' => $includeProgress,
            'include_attendance' => $includeAttendance,
        ]);

        try {
            $enrollments = $this->enrollmentRepository->findEnrollmentsWithFilters($filters);
            $this->logger->info('Enrollments loaded for export', [
                'user_id' => $userId,
                'enrollments_count' => count($enrollments),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to load enrollments for export', [
                'user_id' => $userId,
                'filters' => $filters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new Exception('Erreur lors du chargement des inscriptions pour l\'export : ' . $e->getMessage());
        }

        $response = new StreamedResponse();
        $response->setCallback(function () use ($enrollments, $format, $includeProgress, $includeAttendance, $userId) {
            $this->logger->info('Starting export stream callback', [
                'user_id' => $userId,
                'format' => $format,
                'enrollments_count' => count($enrollments),
            ]);

            try {
                $handle = fopen('php://output', 'w');

                if ($format === 'csv') {
                    $this->logger->debug('Generating CSV export headers', [
                        'user_id' => $userId,
                    ]);

                    // CSV Export
                    $headers = [
                        'ID', 'Étudiant', 'Email', 'Formation', 'Session', 'Statut',
                        'Date inscription', 'Date fin', 'Source', 'Notes admin',
                    ];

                    if ($includeProgress) {
                        $headers = array_merge($headers, ['Progression %', 'Modules complétés']);
                        $this->logger->debug('Added progress columns to CSV headers', [
                            'user_id' => $userId,
                        ]);
                    }

                    if ($includeAttendance) {
                        $headers = array_merge($headers, ['Présences', 'Absences']);
                        $this->logger->debug('Added attendance columns to CSV headers', [
                            'user_id' => $userId,
                        ]);
                    }

                    fputcsv($handle, $headers);

                    $processedCount = 0;
                    foreach ($enrollments as $enrollment) {
                        try {
                            $row = [
                                $enrollment->getId(),
                                $enrollment->getStudent()->getFullName(),
                                $enrollment->getStudent()->getEmail(),
                                $enrollment->getFormation()->getTitle(),
                                $enrollment->getSession()->getName(),
                                $enrollment->getStatusLabel(),
                                $enrollment->getEnrolledAt()->format('Y-m-d H:i:s'),
                                $enrollment->getCompletedAt()?->format('Y-m-d H:i:s') ?? '',
                                $enrollment->getEnrollmentSource(),
                                $enrollment->getAdminNotes() ?? '',
                            ];

                            if ($includeProgress && $enrollment->getProgress()) {
                                $progress = $enrollment->getProgress();
                                $row[] = $progress->getCompletionPercentage();

                                // Calculate completed modules count from module progress
                                $moduleProgress = $progress->getModuleProgress();
                                $completedModulesCount = 0;
                                foreach ($moduleProgress as $module) {
                                    if (isset($module['completed']) && $module['completed']) {
                                        $completedModulesCount++;
                                    }
                                }
                                $row[] = $completedModulesCount;
                            } elseif ($includeProgress) {
                                $row[] = '0';
                                $row[] = '0';
                            }

                            if ($includeAttendance) {
                                $attendanceRecords = $enrollment->getAttendanceRecords();
                                $present = $attendanceRecords->filter(static fn ($record) => $record->isPresent())->count();
                                $absent = $attendanceRecords->count() - $present;
                                $row[] = $present;
                                $row[] = $absent;
                            }

                            fputcsv($handle, $row);
                            $processedCount++;

                            // Log progress every 100 rows
                            if ($processedCount % 100 === 0) {
                                $this->logger->debug('Export progress update', [
                                    'user_id' => $userId,
                                    'processed_count' => $processedCount,
                                    'total_count' => count($enrollments),
                                ]);
                            }
                        } catch (Exception $e) {
                            $this->logger->error('Error processing enrollment row for export', [
                                'user_id' => $userId,
                                'enrollment_id' => $enrollment->getId(),
                                'error' => $e->getMessage(),
                            ]);
                            // Continue with next enrollment
                        }
                    }

                    $this->logger->info('CSV export generation completed', [
                        'user_id' => $userId,
                        'total_enrollments' => count($enrollments),
                        'processed_count' => $processedCount,
                    ]);
                }

                fclose($handle);
            } catch (Exception $e) {
                $this->logger->error('Export stream callback failed', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                if (isset($handle) && $handle) {
                    fclose($handle);
                }

                throw $e;
            }
        });

        $filename = sprintf('enrollments_export_%s.%s', date('Y-m-d_H-i-s'), $format);
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        $this->logger->info('Export file response prepared', [
            'user_id' => $userId,
            'filename' => $filename,
            'format' => $format,
        ]);

        return $response;
    }
}
