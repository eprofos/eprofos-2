<?php

declare(strict_types=1);

namespace App\Controller\Admin\Student;

use App\Entity\Core\StudentEnrollment;
use App\Entity\Training\Session;
use App\Entity\Training\SessionRegistration;
use App\Entity\User\Student;
use App\Repository\Core\StudentEnrollmentRepository;
use App\Repository\Training\SessionRegistrationRepository;
use App\Repository\Training\SessionRepository;
use App\Repository\User\StudentRepository;
use App\Service\Student\StudentEnrollmentService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
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
        private SessionRepository $sessionRepository
    ) {
    }

    /**
     * Enrollment management dashboard.
     */
    #[Route('/', name: 'admin_student_enrollment_index', methods: ['GET'])]
    public function index(Request $request, PaginatorInterface $paginator): Response
    {
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $source = $request->query->get('source', '');

        $criteria = array_filter([
            'status' => $status,
            'enrollmentSource' => $source,
        ]);

        $queryBuilder = $this->enrollmentRepository->createEnrollmentQueryBuilder();

        if ($search) {
            $queryBuilder
                ->andWhere('st.firstName LIKE :search OR st.lastName LIKE :search OR st.email LIKE :search OR f.title LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($status) {
            $queryBuilder
                ->andWhere('se.status = :status')
                ->setParameter('status', $status);
        }

        if ($source) {
            $queryBuilder
                ->andWhere('se.enrollmentSource = :source')
                ->setParameter('source', $source);
        }

        $queryBuilder->orderBy('se.enrolledAt', 'DESC');

        $enrollments = $paginator->paginate(
            $queryBuilder->getQuery(),
            $request->query->getInt('page', 1),
            20
        );

        // Get statistics
        $stats = $this->enrollmentRepository->getEnrollmentStats();
        $linkingStats = $this->enrollmentService->getLinkingStats();

        return $this->render('admin/student/enrollment/index.html.twig', [
            'enrollments' => $enrollments,
            'stats' => $stats,
            'linkingStats' => $linkingStats,
            'search' => $search,
            'status' => $status,
            'source' => $source,
            'statuses' => StudentEnrollment::STATUSES,
        ]);
    }

    /**
     * Manual student linking interface.
     */
    #[Route('/link', name: 'admin_student_enrollment_link', methods: ['GET', 'POST'])]
    public function linkStudent(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            try {
                $studentId = $request->request->get('student_id');
                $registrationId = $request->request->get('registration_id');
                $notes = $request->request->get('notes', '');

                if (!$studentId || !$registrationId) {
                    throw new \Exception('Student and registration must be selected');
                }

                $student = $this->studentRepository->find($studentId);
                $registration = $this->sessionRegistrationRepository->find($registrationId);

                if (!$student || !$registration) {
                    throw new \Exception('Student or registration not found');
                }

                $enrollment = $this->enrollmentService->linkStudentToSessionRegistration($student, $registration);
                
                if ($notes) {
                    $enrollment->setAdminNotes($notes);
                    $this->entityManager->flush();
                }

                $this->addFlash('success', sprintf(
                    'Étudiant %s lié avec succès à la session %s',
                    $student->getFullName(),
                    $registration->getSession()->getName()
                ));

                return $this->redirectToRoute('admin_student_enrollment_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la liaison : ' . $e->getMessage());
            }
        }

        // Get unlinked confirmed registrations
        $unlinkedRegistrations = $this->sessionRegistrationRepository->findUnlinkedConfirmedRegistrations();
        
        return $this->render('admin/student/enrollment/link.html.twig', [
            'unlinkedRegistrations' => $unlinkedRegistrations,
        ]);
    }

    /**
     * AJAX endpoint to search students for linking.
     */
    #[Route('/search-students', name: 'admin_student_enrollment_search_students', methods: ['GET'])]
    public function searchStudents(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        
        if (strlen($query) < 2) {
            return new JsonResponse([]);
        }

        $students = $this->studentRepository->createQueryBuilder('s')
            ->where('s.firstName LIKE :query OR s.lastName LIKE :query OR s.email LIKE :query')
            ->andWhere('s.isActive = :active')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('active', true)
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        $results = [];
        foreach ($students as $student) {
            $results[] = [
                'id' => $student->getId(),
                'text' => sprintf('%s (%s)', $student->getFullName(), $student->getEmail()),
                'email' => $student->getEmail(),
            ];
        }

        return new JsonResponse($results);
    }

    /**
     * Bulk enrollment interface.
     */
    #[Route('/bulk-enroll', name: 'admin_student_enrollment_bulk', methods: ['GET', 'POST'])]
    public function bulkEnroll(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            try {
                $sessionId = $request->request->get('session_id');
                $studentIds = $request->request->all('student_ids');

                if (!$sessionId || empty($studentIds)) {
                    throw new \Exception('Session and students must be selected');
                }

                $session = $this->sessionRepository->find($sessionId);
                if (!$session) {
                    throw new \Exception('Session not found');
                }

                $students = $this->studentRepository->findBy(['id' => $studentIds]);
                if (empty($students)) {
                    throw new \Exception('No valid students found');
                }

                $results = $this->enrollmentService->bulkEnrollStudents($session, $students);

                $this->addFlash('success', sprintf(
                    'Inscription en lot terminée: %d succès, %d échecs',
                    $results['success'],
                    $results['failed']
                ));

                if ($results['failed'] > 0) {
                    $this->addFlash('warning', 'Certaines inscriptions ont échoué. Voir les détails ci-dessous.');
                }

                return $this->render('admin/student/enrollment/bulk_results.html.twig', [
                    'results' => $results,
                    'session' => $session,
                ]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de l\'inscription en lot : ' . $e->getMessage());
            }
        }

        // Get available sessions
        $sessions = $this->sessionRepository->createQueryBuilder('s')
            ->leftJoin('s.formation', 'f')
            ->where('s.startDate > :now OR s.endDate IS NULL')
            ->setParameter('now', new \DateTime())
            ->orderBy('s.startDate', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/student/enrollment/bulk.html.twig', [
            'sessions' => $sessions,
        ]);
    }

    /**
     * Auto-linking management.
     */
    #[Route('/auto-link', name: 'admin_student_enrollment_auto_link', methods: ['GET', 'POST'])]
    public function autoLink(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');
            
            if ($action === 'preview') {
                // Show potential matches for review
                $potentialMatches = $this->enrollmentService->findPotentialAutoLinks();
                
                return $this->render('admin/student/enrollment/auto_link_preview.html.twig', [
                    'potentialMatches' => $potentialMatches,
                ]);
            } elseif ($action === 'process') {
                try {
                    $results = $this->enrollmentService->processAllAutoLinks();
                    
                    $this->addFlash('success', sprintf(
                        'Liaison automatique terminée: %d traitées, %d succès, %d échecs',
                        $results['processed'],
                        $results['success'],
                        $results['failed']
                    ));

                    return $this->render('admin/student/enrollment/auto_link_results.html.twig', [
                        'results' => $results,
                    ]);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur lors de la liaison automatique : ' . $e->getMessage());
                }
            }
        }

        // Get current statistics
        $linkingStats = $this->enrollmentService->getLinkingStats();
        $potentialMatches = $this->enrollmentService->findPotentialAutoLinks();

        return $this->render('admin/student/enrollment/auto_link.html.twig', [
            'linkingStats' => $linkingStats,
            'potentialMatchesCount' => count($potentialMatches),
        ]);
    }

    /**
     * Unlink student from session registration.
     */
    #[Route('/{id}/unlink', name: 'admin_student_enrollment_unlink', methods: ['POST'])]
    public function unlinkStudent(StudentEnrollment $enrollment, Request $request): Response
    {
        try {
            $this->enrollmentService->unlinkStudentFromSessionRegistration($enrollment);
            
            $this->addFlash('success', 'Étudiant délié avec succès de la session');
        } catch (\Exception $e) {
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
        return $this->render('admin/student/enrollment/show.html.twig', [
            'enrollment' => $enrollment,
        ]);
    }

    /**
     * Update enrollment status or notes.
     */
    #[Route('/{id}/edit', name: 'admin_student_enrollment_edit', methods: ['GET', 'POST'])]
    public function edit(StudentEnrollment $enrollment, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            try {
                $status = $request->request->get('status');
                $adminNotes = $request->request->get('admin_notes');
                $dropoutReason = $request->request->get('dropout_reason');

                if ($status && in_array($status, array_keys(StudentEnrollment::STATUSES))) {
                    $enrollment->setStatus($status);
                    
                    if ($status === StudentEnrollment::STATUS_DROPPED_OUT && $dropoutReason) {
                        $enrollment->setDropoutReason($dropoutReason);
                    }
                }

                if ($adminNotes !== null) {
                    $enrollment->setAdminNotes($adminNotes);
                }

                $this->entityManager->flush();

                $this->addFlash('success', 'Inscription mise à jour avec succès');
                
                return $this->redirectToRoute('admin_student_enrollment_show', ['id' => $enrollment->getId()]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la mise à jour : ' . $e->getMessage());
            }
        }

        return $this->render('admin/student/enrollment/edit.html.twig', [
            'enrollment' => $enrollment,
            'statuses' => StudentEnrollment::STATUSES,
        ]);
    }

    /**
     * Enrollment statistics API endpoint.
     */
    #[Route('/api/stats', name: 'admin_student_enrollment_stats_api', methods: ['GET'])]
    public function statsApi(): JsonResponse
    {
        $stats = $this->enrollmentRepository->getEnrollmentStats();
        $linkingStats = $this->enrollmentService->getLinkingStats();

        return new JsonResponse([
            'enrollment' => $stats,
            'linking' => $linkingStats,
        ]);
    }
}
