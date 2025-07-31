<?php

declare(strict_types=1);

namespace App\Controller\Admin\Student;

use App\Entity\Core\StudentEnrollment;
use App\Entity\Training\Session;
use App\Entity\User\Student;
use App\Repository\Core\StudentEnrollmentRepository;
use App\Repository\Training\SessionRepository;
use App\Repository\User\StudentRepository;
use App\Service\Student\StudentEnrollmentService;
use Doctrine\ORM\EntityManagerInterface;
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
        private ValidatorInterface $validator
    ) {
    }

    /**
     * Bulk enrollment interface.
     */
    #[Route('/enroll', name: 'admin_enrollment_bulk_enroll', methods: ['GET', 'POST'])]
    public function bulkEnroll(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            try {
                $sessionId = $request->request->get('session_id');
                $enrollmentMethod = $request->request->get('enrollment_method');
                $notifyStudents = $request->request->getBoolean('notify_students', false);
                $adminNotes = $request->request->get('admin_notes', '');

                if (!$sessionId) {
                    throw new \Exception('Une session doit être sélectionnée');
                }

                $session = $this->sessionRepository->find($sessionId);
                if (!$session) {
                    throw new \Exception('Session non trouvée');
                }

                $students = [];

                if ($enrollmentMethod === 'manual_selection') {
                    $studentIds = $request->request->all('student_ids');
                    if (empty($studentIds)) {
                        throw new \Exception('Aucun étudiant sélectionné');
                    }
                    $students = $this->studentRepository->findBy(['id' => $studentIds]);
                } elseif ($enrollmentMethod === 'csv_upload') {
                    $csvFile = $request->files->get('csv_file');
                    if (!$csvFile) {
                        throw new \Exception('Fichier CSV requis');
                    }
                    $students = $this->processCSVFile($csvFile);
                } elseif ($enrollmentMethod === 'criteria_based') {
                    $criteria = $this->buildCriteriaFromRequest($request);
                    $students = $this->studentRepository->findByCriteria($criteria);
                }

                if (empty($students)) {
                    throw new \Exception('Aucun étudiant valide trouvé');
                }

                $results = $this->enrollmentService->bulkEnrollStudents(
                    $session, 
                    $students, 
                    $adminNotes,
                    $notifyStudents
                );

                return $this->render('admin/student/enrollment/bulk_enroll_results.html.twig', [
                    'results' => $results,
                    'session' => $session,
                    'notifyStudents' => $notifyStudents,
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
        if ($request->isMethod('POST')) {
            try {
                $enrollmentIds = $request->request->all('enrollment_ids');
                $newStatus = $request->request->get('new_status');
                $dropoutReason = $request->request->get('dropout_reason', '');
                $adminNotes = $request->request->get('admin_notes', '');
                $notifyStudents = $request->request->getBoolean('notify_students', false);

                if (empty($enrollmentIds) || !$newStatus) {
                    throw new \Exception('Inscriptions et nouveau statut requis');
                }

                if (!in_array($newStatus, array_keys(StudentEnrollment::STATUSES))) {
                    throw new \Exception('Statut invalide');
                }

                $enrollments = $this->enrollmentRepository->findBy(['id' => $enrollmentIds]);
                
                if (empty($enrollments)) {
                    throw new \Exception('Aucune inscription valide trouvée');
                }

                $results = $this->enrollmentService->bulkUpdateStatus(
                    $enrollments,
                    $newStatus,
                    $dropoutReason,
                    $adminNotes,
                    $notifyStudents
                );

                return $this->render('admin/student/enrollment/bulk_status_results.html.twig', [
                    'results' => $results,
                    'newStatus' => $newStatus,
                    'statusLabel' => StudentEnrollment::STATUSES[$newStatus],
                ]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors du changement de statut en lot : ' . $e->getMessage());
            }
        }

        // Get enrollments with filtering
        $filters = $this->buildFiltersFromRequest($request);
        $enrollments = $this->enrollmentRepository->findEnrollmentsWithFilters($filters);

        return $this->render('admin/student/enrollment/bulk_status_change.html.twig', [
            'enrollments' => $enrollments,
            'statuses' => StudentEnrollment::STATUSES,
            'filters' => $filters,
        ]);
    }

    /**
     * Bulk export interface.
     */
    #[Route('/export', name: 'admin_enrollment_bulk_export', methods: ['GET', 'POST'])]
    public function bulkExport(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            // Handle AJAX preview requests
            if ($request->isXmlHttpRequest()) {
                $filters = $this->buildFiltersFromRequest($request);
                $enrollmentsCount = $this->enrollmentRepository->countEnrollmentsWithFilters($filters);
                
                return new JsonResponse([
                    'success' => true,
                    'count' => $enrollmentsCount,
                ]);
            }

            // Handle actual export requests
            $format = $request->request->get('format', 'csv');
            $filters = $this->buildFiltersFromRequest($request);
            $includeProgress = $request->request->getBoolean('include_progress', false);
            $includeAttendance = $request->request->getBoolean('include_attendance', false);

            return $this->generateExport($format, $filters, $includeProgress, $includeAttendance);
        }

        // Get current filters for preview
        $filters = $this->buildFiltersFromRequest($request);
        $enrollmentsCount = $this->enrollmentRepository->countEnrollmentsWithFilters($filters);

        // Get formations for the dropdown
        $formations = $this->entityManager->getRepository(\App\Entity\Training\Formation::class)
            ->createQueryBuilder('f')
            ->select('f.id, f.title')
            ->where('f.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('f.title', 'ASC')
            ->getQuery()
            ->getArrayResult();

        // Get sessions for the dropdown  
        $sessions = $this->sessionRepository->createQueryBuilder('s')
            ->select('s.id, s.name, f.id as formation_id, f.title as formationTitle')
            ->leftJoin('s.formation', 'f')
            ->where('s.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('f.title, s.name', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return $this->render('admin/student/enrollment/bulk_export.html.twig', [
            'enrollmentsCount' => $enrollmentsCount,
            'filters' => $filters,
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
        if ($request->isMethod('POST')) {
            try {
                $enrollmentIds = $request->request->all('enrollment_ids');
                $reason = $request->request->get('unenroll_reason', 'Admin bulk unenrollment');
                $notifyStudents = $request->request->getBoolean('notify_students', false);

                if (empty($enrollmentIds)) {
                    throw new \Exception('Aucune inscription sélectionnée');
                }

                $enrollments = $this->enrollmentRepository->findBy(['id' => $enrollmentIds]);
                
                if (empty($enrollments)) {
                    throw new \Exception('Aucune inscription valide trouvée');
                }

                $results = $this->enrollmentService->bulkUnenroll(
                    $enrollments,
                    $reason,
                    $notifyStudents
                );

                return $this->render('admin/student/enrollment/bulk_unenroll_results.html.twig', [
                    'results' => $results,
                    'reason' => $reason,
                ]);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la désinscription en lot : ' . $e->getMessage());
            }
        }

        // Get active enrollments only
        $filters = array_merge($this->buildFiltersFromRequest($request), ['status' => StudentEnrollment::STATUS_ENROLLED]);
        $enrollments = $this->enrollmentRepository->findEnrollmentsWithFilters($filters);

        return $this->render('admin/student/enrollment/bulk_unenroll.html.twig', [
            'enrollments' => $enrollments,
            'filters' => $filters,
        ]);
    }

    /**
     * AJAX endpoint for student search with criteria.
     */
    #[Route('/search-students', name: 'admin_enrollment_bulk_search_students', methods: ['POST'])]
    public function searchStudents(Request $request): JsonResponse
    {
        try {
            $criteria = $this->buildCriteriaFromRequest($request);
            $students = $this->studentRepository->findByCriteria($criteria, 100); // Limit to 100 for performance

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

            return new JsonResponse([
                'success' => true,
                'students' => $results,
                'count' => count($results),
            ]);
        } catch (\Exception $e) {
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
        try {
            $sessionId = $request->request->get('session_id');
            $studentIds = $request->request->all('student_ids');

            if (!$sessionId || empty($studentIds)) {
                throw new \Exception('Session et étudiants requis');
            }

            $session = $this->sessionRepository->find($sessionId);
            $students = $this->studentRepository->findBy(['id' => $studentIds]);

            $validation = $this->enrollmentService->validateBulkEnrollment($session, $students);

            return new JsonResponse([
                'success' => true,
                'validation' => $validation,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Process CSV file for bulk enrollment.
     */
    private function processCSVFile($csvFile): array
    {
        $students = [];
        $handle = fopen($csvFile->getPathname(), 'r');
        
        if (!$handle) {
            throw new \Exception('Impossible de lire le fichier CSV');
        }

        $header = fgetcsv($handle); // Skip header row
        $expectedHeaders = ['email', 'first_name', 'last_name'];
        
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 3) {
                continue; // Skip invalid rows
            }

            $email = trim($row[0]);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue; // Skip invalid emails
            }

            $student = $this->studentRepository->findOneBy(['email' => $email]);
            if ($student) {
                $students[] = $student;
            }
        }

        fclose($handle);
        return $students;
    }

    /**
     * Build criteria array from request parameters.
     */
    private function buildCriteriaFromRequest(Request $request): array
    {
        $criteria = [];

        if ($education = $request->get('education')) {
            $criteria['education'] = $education;
        }

        if ($profession = $request->get('profession')) {
            $criteria['profession'] = $profession;
        }

        if ($location = $request->get('location')) {
            $criteria['location'] = $location;
        }

        if ($ageMin = $request->get('age_min')) {
            $criteria['age_min'] = (int) $ageMin;
        }

        if ($ageMax = $request->get('age_max')) {
            $criteria['age_max'] = (int) $ageMax;
        }

        if ($hasCompletedFormations = $request->get('has_completed_formations')) {
            $criteria['has_completed_formations'] = $hasCompletedFormations === 'true';
        }

        return $criteria;
    }

    /**
     * Build filters array from request parameters.
     */
    private function buildFiltersFromRequest(Request $request): array
    {
        $filters = [];

        if ($status = $request->get('status')) {
            $filters['status'] = $status;
        }

        if ($formation = $request->get('formation')) {
            $filters['formation'] = $formation;
        }

        if ($session = $request->get('session')) {
            $filters['session'] = $session;
        }

        if ($enrollmentSource = $request->get('enrollment_source')) {
            $filters['enrollment_source'] = $enrollmentSource;
        }

        if ($enrolledAfter = $request->get('enrolled_after')) {
            $filters['enrolled_after'] = new \DateTime($enrolledAfter);
        }

        if ($enrolledBefore = $request->get('enrolled_before')) {
            $filters['enrolled_before'] = new \DateTime($enrolledBefore);
        }

        if ($studentSearch = $request->get('student_search')) {
            $filters['student_search'] = $studentSearch;
        }

        return $filters;
    }

    /**
     * Generate export file.
     */
    private function generateExport(string $format, array $filters, bool $includeProgress, bool $includeAttendance): StreamedResponse
    {
        $enrollments = $this->enrollmentRepository->findEnrollmentsWithFilters($filters);

        $response = new StreamedResponse();
        $response->setCallback(function () use ($enrollments, $format, $includeProgress, $includeAttendance) {
            $handle = fopen('php://output', 'w');

            if ($format === 'csv') {
                // CSV Export
                $headers = [
                    'ID', 'Étudiant', 'Email', 'Formation', 'Session', 'Statut', 
                    'Date inscription', 'Date fin', 'Source', 'Notes admin'
                ];

                if ($includeProgress) {
                    $headers = array_merge($headers, ['Progression %', 'Modules complétés']);
                }

                if ($includeAttendance) {
                    $headers = array_merge($headers, ['Présences', 'Absences']);
                }

                fputcsv($handle, $headers);

                foreach ($enrollments as $enrollment) {
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
                        $present = $attendanceRecords->filter(fn($record) => $record->isPresent())->count();
                        $absent = $attendanceRecords->count() - $present;
                        $row[] = $present;
                        $row[] = $absent;
                    }

                    fputcsv($handle, $row);
                }
            }

            fclose($handle);
        });

        $filename = sprintf('enrollments_export_%s.%s', date('Y-m-d_H-i-s'), $format);
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }
}
