<?php

declare(strict_types=1);

namespace App\Controller\Admin\Student;

use App\Entity\Core\StudentEnrollment;
use App\Entity\Training\Session;
use App\Entity\User\Student;
use App\Repository\Core\StudentEnrollmentRepository;
use App\Repository\Training\SessionRegistrationRepository;
use App\Repository\Training\SessionRepository;
use App\Repository\User\StudentRepository;
use App\Service\Student\StudentEnrollmentService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * EnrollmentController manages student enrollments and linking to session registrations.
 *
 * Provides admin interface for manual student linking, bulk enrollment, and
 * enrollment management as part of the Student Content Access System.
 */
#[Route('/admin/student/enrollment')]
#[IsGranted('ROLE_ADMIN')]
class EnrollmentController extends AbstractController
{
    public function __construct(
        private StudentEnrollmentService $enrollmentService,
        private EntityManagerInterface $entityManager,
        private StudentEnrollmentRepository $enrollmentRepository,
        private SessionRegistrationRepository $sessionRegistrationRepository,
        private StudentRepository $studentRepository,
        private SessionRepository $sessionRepository,
        private LoggerInterface $logger,
    ) {}

    /**
     * Enrollment management dashboard.
     */
    #[Route('/', name: 'admin_student_enrollment_index', methods: ['GET'])]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $this->logger->info('Starting enrollment management dashboard', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'ip_address' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
        ]);

        try {
            $search = $request->query->get('search', '');
            $status = $request->query->get('status', '');
            $source = $request->query->get('source', '');
            $page = $request->query->getInt('page', 1);

            $this->logger->debug('Processing enrollment index filters', [
                'search' => $search,
                'status' => $status,
                'source' => $source,
                'page' => $page,
            ]);

            $criteria = array_filter([
                'status' => $status,
                'enrollmentSource' => $source,
            ]);

            $queryBuilder = $this->enrollmentRepository->createEnrollmentQueryBuilder();

            if ($search) {
                $this->logger->debug('Applying search filter to enrollment query', [
                    'search_term' => $search,
                ]);
                $queryBuilder
                    ->andWhere('st.firstName LIKE :search OR st.lastName LIKE :search OR st.email LIKE :search OR f.title LIKE :search')
                    ->setParameter('search', '%' . $search . '%')
                ;
            }

            if ($status) {
                $this->logger->debug('Applying status filter to enrollment query', [
                    'status' => $status,
                ]);
                $queryBuilder
                    ->andWhere('se.status = :status')
                    ->setParameter('status', $status)
                ;
            }

            if ($source) {
                $this->logger->debug('Applying source filter to enrollment query', [
                    'source' => $source,
                ]);
                $queryBuilder
                    ->andWhere('se.enrollmentSource = :source')
                    ->setParameter('source', $source)
                ;
            }

            $queryBuilder->orderBy('se.enrolledAt', 'DESC');

            $enrollments = $paginator->paginate(
                $queryBuilder->getQuery(),
                $page,
                20,
            );

            // Get statistics
            $this->logger->debug('Retrieving enrollment statistics');
            $stats = $this->enrollmentRepository->getEnrollmentStats();
            $linkingStats = $this->enrollmentService->getLinkingStats();

            $this->logger->info('Successfully loaded enrollment dashboard', [
                'total_enrollments' => $enrollments->getTotalItemCount(),
                'current_page' => $page,
                'items_per_page' => 20,
                'search_applied' => !empty($search),
                'filters_applied' => count($criteria),
            ]);

            return $this->render('admin/student/enrollment/index.html.twig', [
                'enrollments' => $enrollments,
                'stats' => $stats,
                'linkingStats' => $linkingStats,
                'search' => $search,
                'status' => $status,
                'source' => $source,
                'statuses' => StudentEnrollment::STATUSES,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error loading enrollment dashboard', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'request_params' => $request->query->all(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement du tableau de bord des inscriptions.');

            // Return empty dashboard with error state
            return $this->render('admin/student/enrollment/index.html.twig', [
                'enrollments' => [],
                'stats' => [],
                'linkingStats' => [],
                'search' => '',
                'status' => '',
                'source' => '',
                'statuses' => StudentEnrollment::STATUSES,
                'error' => true,
            ]);
        }
    }

    /**
     * Manual student linking interface.
     */
    #[Route('/link', name: 'admin_student_enrollment_link', methods: ['GET', 'POST'])]
    public function linkStudent(Request $request): Response
    {
        $this->logger->info('Accessing manual student linking interface', [
            'method' => $request->getMethod(),
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'ip_address' => $request->getClientIp(),
        ]);

        if ($request->isMethod('POST')) {
            try {
                $studentId = $request->request->get('student_id');
                $registrationId = $request->request->get('registration_id');
                $notes = $request->request->get('notes', '');

                $this->logger->info('Processing manual student linking request', [
                    'student_id' => $studentId,
                    'registration_id' => $registrationId,
                    'has_notes' => !empty($notes),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);

                if (!$studentId || !$registrationId) {
                    $this->logger->warning('Invalid linking request - missing required parameters', [
                        'student_id' => $studentId,
                        'registration_id' => $registrationId,
                    ]);

                    throw new Exception('Student and registration must be selected');
                }

                $student = $this->studentRepository->find($studentId);
                $registration = $this->sessionRegistrationRepository->find($registrationId);

                if (!$student) {
                    $this->logger->error('Student not found for linking', [
                        'student_id' => $studentId,
                    ]);

                    throw new Exception('Student not found');
                }

                if (!$registration) {
                    $this->logger->error('Registration not found for linking', [
                        'registration_id' => $registrationId,
                    ]);

                    throw new Exception('Registration not found');
                }

                $this->logger->debug('Found entities for linking', [
                    'student_email' => $student->getEmail(),
                    'student_name' => $student->getFullName(),
                    'session_name' => $registration->getSession()->getName(),
                    'registration_status' => $registration->getStatus(),
                ]);

                $enrollment = $this->enrollmentService->linkStudentToSessionRegistration($student, $registration);

                if ($notes) {
                    $this->logger->debug('Adding admin notes to enrollment', [
                        'enrollment_id' => $enrollment->getId(),
                        'notes_length' => strlen($notes),
                    ]);
                    $enrollment->setAdminNotes($notes);
                    $this->entityManager->flush();
                }

                $this->logger->info('Successfully linked student to session registration', [
                    'enrollment_id' => $enrollment->getId(),
                    'student_id' => $student->getId(),
                    'registration_id' => $registration->getId(),
                    'session_id' => $registration->getSession()->getId(),
                    'enrollment_source' => $enrollment->getEnrollmentSource(),
                ]);

                $this->addFlash('success', sprintf(
                    'Étudiant %s lié avec succès à la session %s',
                    $student->getFullName(),
                    $registration->getSession()->getName(),
                ));

                return $this->redirectToRoute('admin_student_enrollment_index');
            } catch (Exception $e) {
                $this->logger->error('Error during manual student linking', [
                    'error_message' => $e->getMessage(),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine(),
                    'stack_trace' => $e->getTraceAsString(),
                    'request_data' => $request->request->all(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('error', 'Erreur lors de la liaison : ' . $e->getMessage());
            }
        }

        try {
            // Get unlinked confirmed registrations
            $this->logger->debug('Fetching unlinked confirmed registrations');
            $unlinkedRegistrations = $this->sessionRegistrationRepository->findUnlinkedConfirmedRegistrations();

            $this->logger->info('Successfully loaded linking interface', [
                'unlinked_registrations_count' => count($unlinkedRegistrations),
            ]);

            return $this->render('admin/student/enrollment/link.html.twig', [
                'unlinkedRegistrations' => $unlinkedRegistrations,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error loading linking interface', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement de l\'interface de liaison.');

            return $this->render('admin/student/enrollment/link.html.twig', [
                'unlinkedRegistrations' => [],
                'error' => true,
            ]);
        }
    }

    /**
     * AJAX endpoint to search students for linking.
     */
    #[Route('/search-students', name: 'admin_student_enrollment_search_students', methods: ['GET'])]
    public function searchStudents(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');

        $this->logger->info('Student search request', [
            'search_query' => $query,
            'query_length' => strlen($query),
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'ip_address' => $request->getClientIp(),
        ]);

        try {
            if (strlen($query) < 2) {
                $this->logger->debug('Search query too short, returning empty results', [
                    'query_length' => strlen($query),
                    'minimum_required' => 2,
                ]);

                return new JsonResponse([]);
            }

            $this->logger->debug('Executing student search query', [
                'search_pattern' => '%' . $query . '%',
                'max_results' => 20,
            ]);

            $students = $this->studentRepository->createQueryBuilder('s')
                ->where('s.firstName LIKE :query OR s.lastName LIKE :query OR s.email LIKE :query')
                ->andWhere('s.isActive = :active')
                ->setParameter('query', '%' . $query . '%')
                ->setParameter('active', true)
                ->setMaxResults(20)
                ->getQuery()
                ->getResult()
            ;

            $results = [];
            foreach ($students as $student) {
                $results[] = [
                    'id' => $student->getId(),
                    'text' => sprintf('%s (%s)', $student->getFullName(), $student->getEmail()),
                    'email' => $student->getEmail(),
                ];
            }

            $this->logger->info('Student search completed successfully', [
                'search_query' => $query,
                'results_count' => count($results),
                'students_found' => array_map(static fn ($r) => $r['email'], $results),
            ]);

            return new JsonResponse($results);
        } catch (Exception $e) {
            $this->logger->error('Error during student search', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'search_query' => $query,
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            return new JsonResponse([
                'error' => 'Une erreur est survenue lors de la recherche d\'étudiants',
            ], 500);
        }
    }

    /**
     * Bulk enrollment interface.
     */
    #[Route('/bulk-enroll', name: 'admin_student_enrollment_bulk', methods: ['GET', 'POST'])]
    public function bulkEnroll(Request $request): Response
    {
        $this->logger->info('Accessing bulk enrollment interface', [
            'method' => $request->getMethod(),
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'ip_address' => $request->getClientIp(),
        ]);

        if ($request->isMethod('POST')) {
            try {
                $sessionId = $request->request->get('session_id');
                $studentIds = $request->request->all('student_ids');

                $this->logger->info('Processing bulk enrollment request', [
                    'session_id' => $sessionId,
                    'student_ids' => $studentIds,
                    'student_count' => count($studentIds),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);

                if (!$sessionId || empty($studentIds)) {
                    $this->logger->warning('Invalid bulk enrollment request - missing required parameters', [
                        'session_id' => $sessionId,
                        'student_ids_count' => count($studentIds),
                    ]);

                    throw new Exception('Session and students must be selected');
                }

                $session = $this->sessionRepository->find($sessionId);
                if (!$session) {
                    $this->logger->error('Session not found for bulk enrollment', [
                        'session_id' => $sessionId,
                    ]);

                    throw new Exception('Session not found');
                }

                $students = $this->studentRepository->findBy(['id' => $studentIds]);
                if (empty($students)) {
                    $this->logger->error('No valid students found for bulk enrollment', [
                        'requested_student_ids' => $studentIds,
                        'found_students_count' => count($students),
                    ]);

                    throw new Exception('No valid students found');
                }

                $this->logger->debug('Found entities for bulk enrollment', [
                    'session_name' => $session->getName(),
                    'session_start_date' => $session->getStartDate()?->format('Y-m-d'),
                    'students_found' => count($students),
                    'student_emails' => array_map(static fn ($s) => $s->getEmail(), $students),
                ]);

                $results = $this->enrollmentService->bulkEnrollStudents($session, $students);

                $this->logger->info('Bulk enrollment completed', [
                    'session_id' => $session->getId(),
                    'success_count' => $results['success'],
                    'failed_count' => $results['failed'],
                    'total_processed' => count($students),
                    'success_rate' => round(($results['success'] / count($students)) * 100, 2) . '%',
                ]);

                $this->addFlash('success', sprintf(
                    'Inscription en lot terminée: %d succès, %d échecs',
                    $results['success'],
                    $results['failed'],
                ));

                if ($results['failed'] > 0) {
                    $this->addFlash('warning', 'Certaines inscriptions ont échoué. Voir les détails ci-dessous.');
                }

                return $this->render('admin/student/enrollment/bulk_results.html.twig', [
                    'results' => $results,
                    'session' => $session,
                ]);
            } catch (Exception $e) {
                $this->logger->error('Error during bulk enrollment', [
                    'error_message' => $e->getMessage(),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine(),
                    'stack_trace' => $e->getTraceAsString(),
                    'request_data' => $request->request->all(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('error', 'Erreur lors de l\'inscription en lot : ' . $e->getMessage());
            }
        }

        try {
            // Get available sessions
            $this->logger->debug('Fetching available sessions for bulk enrollment');
            $sessions = $this->sessionRepository->createQueryBuilder('s')
                ->leftJoin('s.formation', 'f')
                ->where('s.startDate > :now OR s.endDate IS NULL')
                ->setParameter('now', new DateTime())
                ->orderBy('s.startDate', 'ASC')
                ->getQuery()
                ->getResult()
            ;

            $this->logger->info('Successfully loaded bulk enrollment interface', [
                'available_sessions_count' => count($sessions),
                'sessions' => array_map(static fn ($s) => [
                    'id' => $s->getId(),
                    'name' => $s->getName(),
                    'start_date' => $s->getStartDate()?->format('Y-m-d'),
                ], $sessions),
            ]);

            return $this->render('admin/student/enrollment/bulk.html.twig', [
                'sessions' => $sessions,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error loading bulk enrollment interface', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement de l\'interface d\'inscription en lot.');

            return $this->render('admin/student/enrollment/bulk.html.twig', [
                'sessions' => [],
                'error' => true,
            ]);
        }
    }

    /**
     * Auto-linking management.
     */
    #[Route('/auto-link', name: 'admin_student_enrollment_auto_link', methods: ['GET', 'POST'])]
    public function autoLink(Request $request): Response
    {
        $this->logger->info('Accessing auto-linking management interface', [
            'method' => $request->getMethod(),
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'ip_address' => $request->getClientIp(),
        ]);

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');

            $this->logger->info('Processing auto-linking action', [
                'action' => $action,
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            if ($action === 'preview') {
                try {
                    // Show potential matches for review
                    $this->logger->debug('Finding potential auto-links for preview');
                    $potentialMatches = $this->enrollmentService->findPotentialAutoLinks();

                    $this->logger->info('Successfully found potential auto-links', [
                        'potential_matches_count' => count($potentialMatches),
                        'matches_preview' => array_slice(array_map(static fn ($match) => [
                            'student_email' => $match['student']->getEmail() ?? 'unknown',
                            'session_name' => $match['registration']->getSession()->getName() ?? 'unknown',
                            'confidence' => $match['confidence'] ?? 'unknown',
                        ], $potentialMatches), 0, 5), // Log first 5 matches
                    ]);

                    return $this->render('admin/student/enrollment/auto_link_preview.html.twig', [
                        'potentialMatches' => $potentialMatches,
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Error finding potential auto-links', [
                        'error_message' => $e->getMessage(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'stack_trace' => $e->getTraceAsString(),
                        'user_id' => $this->getUser()?->getUserIdentifier(),
                    ]);

                    $this->addFlash('error', 'Erreur lors de la recherche des liaisons potentielles : ' . $e->getMessage());

                    return $this->render('admin/student/enrollment/auto_link_preview.html.twig', [
                        'potentialMatches' => [],
                        'error' => true,
                    ]);
                }
            } elseif ($action === 'process') {
                try {
                    $this->logger->info('Starting auto-linking process');
                    $results = $this->enrollmentService->processAllAutoLinks();

                    $this->logger->info('Auto-linking process completed', [
                        'processed_count' => $results['processed'],
                        'success_count' => $results['success'],
                        'failed_count' => $results['failed'],
                        'success_rate' => $results['processed'] > 0 ?
                            round(($results['success'] / $results['processed']) * 100, 2) . '%' : '0%',
                    ]);

                    $this->addFlash('success', sprintf(
                        'Liaison automatique terminée: %d traitées, %d succès, %d échecs',
                        $results['processed'],
                        $results['success'],
                        $results['failed'],
                    ));

                    return $this->render('admin/student/enrollment/auto_link_results.html.twig', [
                        'results' => $results,
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Error during auto-linking process', [
                        'error_message' => $e->getMessage(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'stack_trace' => $e->getTraceAsString(),
                        'user_id' => $this->getUser()?->getUserIdentifier(),
                    ]);

                    $this->addFlash('error', 'Erreur lors de la liaison automatique : ' . $e->getMessage());
                }
            } else {
                $this->logger->warning('Unknown auto-linking action requested', [
                    'action' => $action,
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);
            }
        }

        try {
            // Get current statistics
            $this->logger->debug('Fetching auto-linking statistics');
            $linkingStats = $this->enrollmentService->getLinkingStats();
            $potentialMatches = $this->enrollmentService->findPotentialAutoLinks();

            $this->logger->info('Successfully loaded auto-linking interface', [
                'potential_matches_count' => count($potentialMatches),
                'linking_stats' => $linkingStats,
            ]);

            return $this->render('admin/student/enrollment/auto_link.html.twig', [
                'linkingStats' => $linkingStats,
                'potentialMatchesCount' => count($potentialMatches),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error loading auto-linking interface', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement de l\'interface de liaison automatique.');

            return $this->render('admin/student/enrollment/auto_link.html.twig', [
                'linkingStats' => [],
                'potentialMatchesCount' => 0,
                'error' => true,
            ]);
        }
    }

    /**
     * Unlink student from session registration.
     */
    #[Route('/{id}/unlink', name: 'admin_student_enrollment_unlink', methods: ['POST'])]
    public function unlinkStudent(StudentEnrollment $enrollment, Request $request): Response
    {
        $this->logger->info('Starting student unlinking process', [
            'enrollment_id' => $enrollment->getId(),
            'student_id' => $enrollment->getStudent()?->getId(),
            'student_email' => $enrollment->getStudent()?->getEmail(),
            'session_id' => $enrollment->getSessionRegistration()?->getSession()?->getId(),
            'session_name' => $enrollment->getSessionRegistration()?->getSession()?->getName(),
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'ip_address' => $request->getClientIp(),
        ]);

        try {
            $this->enrollmentService->unlinkStudentFromSessionRegistration($enrollment);

            $this->logger->info('Successfully unlinked student from session registration', [
                'enrollment_id' => $enrollment->getId(),
                'student_email' => $enrollment->getStudent()?->getEmail(),
                'session_name' => $enrollment->getSessionRegistration()?->getSession()?->getName(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('success', 'Étudiant délié avec succès de la session');
        } catch (Exception $e) {
            $this->logger->error('Error during student unlinking', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'enrollment_id' => $enrollment->getId(),
                'student_email' => $enrollment->getStudent()?->getEmail(),
                'session_name' => $enrollment->getSessionRegistration()?->getSession()?->getName(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Erreur lors de la déliaison : ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_student_enrollment_index');
    }

    /**
     * View enrollment details.
     */
    #[Route('/{id}', name: 'admin_student_enrollment_show', methods: ['GET'])]
    public function show(StudentEnrollment $enrollment): Response
    {
        $this->logger->info('Viewing enrollment details', [
            'enrollment_id' => $enrollment->getId(),
            'student_id' => $enrollment->getStudent()?->getId(),
            'student_email' => $enrollment->getStudent()?->getEmail(),
            'student_name' => $enrollment->getStudent()?->getFullName(),
            'session_id' => $enrollment->getSessionRegistration()?->getSession()?->getId(),
            'session_name' => $enrollment->getSessionRegistration()?->getSession()?->getName(),
            'enrollment_status' => $enrollment->getStatus(),
            'enrollment_source' => $enrollment->getEnrollmentSource(),
            'enrolled_at' => $enrollment->getEnrolledAt()?->format('Y-m-d H:i:s'),
            'user_id' => $this->getUser()?->getUserIdentifier(),
        ]);

        try {
            return $this->render('admin/student/enrollment/show.html.twig', [
                'enrollment' => $enrollment,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error displaying enrollment details', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'enrollment_id' => $enrollment->getId(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'affichage des détails de l\'inscription.');

            return $this->redirectToRoute('admin_student_enrollment_index');
        }
    }

    /**
     * Update enrollment status or notes.
     */
    #[Route('/{id}/edit', name: 'admin_student_enrollment_edit', methods: ['GET', 'POST'])]
    public function edit(StudentEnrollment $enrollment, Request $request): Response
    {
        $this->logger->info('Accessing enrollment edit interface', [
            'method' => $request->getMethod(),
            'enrollment_id' => $enrollment->getId(),
            'current_status' => $enrollment->getStatus(),
            'student_email' => $enrollment->getStudent()?->getEmail(),
            'session_name' => $enrollment->getSessionRegistration()?->getSession()?->getName(),
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'ip_address' => $request->getClientIp(),
        ]);

        if ($request->isMethod('POST')) {
            try {
                $status = $request->request->get('status');
                $adminNotes = $request->request->get('admin_notes');
                $dropoutReason = $request->request->get('dropout_reason');

                $this->logger->info('Processing enrollment update', [
                    'enrollment_id' => $enrollment->getId(),
                    'old_status' => $enrollment->getStatus(),
                    'new_status' => $status,
                    'has_admin_notes' => $adminNotes !== null,
                    'admin_notes_length' => $adminNotes ? strlen($adminNotes) : 0,
                    'dropout_reason' => $dropoutReason,
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);

                $changes = [];

                if ($status && in_array($status, array_keys(StudentEnrollment::STATUSES), true)) {
                    $oldStatus = $enrollment->getStatus();
                    $enrollment->setStatus($status);
                    $changes['status'] = ['old' => $oldStatus, 'new' => $status];

                    if ($status === StudentEnrollment::STATUS_DROPPED_OUT && $dropoutReason) {
                        $enrollment->setDropoutReason($dropoutReason);
                        $changes['dropout_reason'] = $dropoutReason;

                        $this->logger->warning('Student marked as dropped out', [
                            'enrollment_id' => $enrollment->getId(),
                            'student_email' => $enrollment->getStudent()?->getEmail(),
                            'dropout_reason' => $dropoutReason,
                            'session_name' => $enrollment->getSessionRegistration()?->getSession()?->getName(),
                        ]);
                    }
                } elseif ($status && !in_array($status, array_keys(StudentEnrollment::STATUSES), true)) {
                    $this->logger->warning('Invalid status provided for enrollment update', [
                        'enrollment_id' => $enrollment->getId(),
                        'invalid_status' => $status,
                        'valid_statuses' => array_keys(StudentEnrollment::STATUSES),
                    ]);
                }

                if ($adminNotes !== null) {
                    $oldNotes = $enrollment->getAdminNotes();
                    $enrollment->setAdminNotes($adminNotes);
                    $changes['admin_notes'] = [
                        'old_length' => $oldNotes ? strlen($oldNotes) : 0,
                        'new_length' => strlen($adminNotes),
                    ];
                }

                $this->entityManager->flush();

                $this->logger->info('Successfully updated enrollment', [
                    'enrollment_id' => $enrollment->getId(),
                    'changes_made' => $changes,
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('success', 'Inscription mise à jour avec succès');

                return $this->redirectToRoute('admin_student_enrollment_show', ['id' => $enrollment->getId()]);
            } catch (Exception $e) {
                $this->logger->error('Error updating enrollment', [
                    'error_message' => $e->getMessage(),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine(),
                    'stack_trace' => $e->getTraceAsString(),
                    'enrollment_id' => $enrollment->getId(),
                    'request_data' => $request->request->all(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                ]);

                $this->addFlash('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
            }
        }

        try {
            return $this->render('admin/student/enrollment/edit.html.twig', [
                'enrollment' => $enrollment,
                'statuses' => StudentEnrollment::STATUSES,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error loading enrollment edit form', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'enrollment_id' => $enrollment->getId(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement du formulaire de modification.');

            return $this->redirectToRoute('admin_student_enrollment_show', ['id' => $enrollment->getId()]);
        }
    }

    /**
     * Enrollment statistics API endpoint.
     */
    #[Route('/api/stats', name: 'admin_student_enrollment_stats_api', methods: ['GET'])]
    public function statsApi(): JsonResponse
    {
        $this->logger->info('API request for enrollment statistics', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'timestamp' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);

        try {
            $this->logger->debug('Fetching enrollment statistics from repository');
            $stats = $this->enrollmentRepository->getEnrollmentStats();

            $this->logger->debug('Fetching linking statistics from service');
            $linkingStats = $this->enrollmentService->getLinkingStats();

            $response = [
                'enrollment' => $stats,
                'linking' => $linkingStats,
            ];

            $this->logger->info('Successfully retrieved enrollment statistics via API', [
                'enrollment_stats_keys' => array_keys($stats),
                'linking_stats_keys' => array_keys($linkingStats),
                'response_size' => strlen(json_encode($response)),
            ]);

            return new JsonResponse($response);
        } catch (Exception $e) {
            $this->logger->error('Error retrieving enrollment statistics via API', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
            ]);

            return new JsonResponse([
                'error' => 'Une erreur est survenue lors de la récupération des statistiques',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
