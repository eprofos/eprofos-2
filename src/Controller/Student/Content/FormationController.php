<?php

declare(strict_types=1);

namespace App\Controller\Student\Content;

use App\Entity\Training\Formation;
use App\Entity\User\Student;
use App\Repository\Training\FormationRepository;
use App\Service\Security\ContentAccessService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Student Formation Controller.
 *
 * Handles formation content access for enrolled students with proper
 * access control and security checks.
 */
#[Route('/student/formation')]
#[IsGranted('ROLE_STUDENT')]
class FormationController extends AbstractController
{
    public function __construct(
        private readonly FormationRepository $formationRepository,
        private readonly ContentAccessService $contentAccessService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * List all accessible formations for the current student.
     */
    #[Route('/', name: 'student_formation_index', methods: ['GET'])]
    public function index(): Response
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student accessing formations index', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'ip_address' => $this->container->get('request_stack')->getCurrentRequest()?->getClientIp(),
                'user_agent' => $this->container->get('request_stack')->getCurrentRequest()?->headers->get('User-Agent'),
                'timestamp' => new \DateTime(),
            ]);

            // Get formations accessible to this student
            $this->logger->debug('Retrieving accessible formations for student', [
                'student_id' => $student->getId(),
            ]);

            $accessibleFormations = $this->contentAccessService->getAccessibleFormations($student);

            $this->logger->info('Accessible formations retrieved successfully', [
                'student_id' => $student->getId(),
                'total_formations' => count($accessibleFormations),
                'formation_details' => array_map(fn($formation) => [
                    'formation_id' => $formation->getId(),
                    'formation_title' => $formation->getTitle(),
                    'formation_level' => $formation->getLevel(),
                    'formation_duration_hours' => $formation->getDurationHours(),
                    'formation_category' => $formation->getCategory()?->getName(),
                    'is_active' => $formation->isActive(),
                ], $accessibleFormations),
            ]);

            $this->logger->info('Formations index view successful', [
                'student_id' => $student->getId(),
                'formations_count' => count($accessibleFormations),
            ]);

            return $this->render('student/content/formation/index.html.twig', [
                'formations' => $accessibleFormations,
                'student' => $student,
                'page_title' => 'Mes Formations',
            ]);

        } catch (\InvalidArgumentException $e) {
            $this->logger->error('Invalid argument provided for formations index', [
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $this->addFlash('error', 'Paramètres invalides pour l\'accès aux formations.');
            return $this->redirectToRoute('student_dashboard');

        } catch (\LogicException $e) {
            $this->logger->error('Logic error in formations index process', [
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $this->addFlash('error', 'Erreur dans la logique d\'accès aux formations.');
            return $this->redirectToRoute('student_dashboard');

        } catch (\Exception $e) {
            $this->logger->critical('Unexpected error during formations index', [
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_uri' => $this->container->get('request_stack')->getCurrentRequest()?->getRequestUri(),
                'request_method' => $this->container->get('request_stack')->getCurrentRequest()?->getMethod(),
            ]);
            
            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de l\'accès aux formations.');
            return $this->redirectToRoute('student_dashboard');
        }
    }

    /**
     * View a specific formation with access control.
     */
    #[Route('/{id}', name: 'student_formation_view', methods: ['GET'])]
    #[IsGranted('view', subject: 'formation')]
    public function view(Formation $formation): Response
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student attempting to view formation', [
                'formation_id' => $formation->getId(),
                'formation_title' => $formation->getTitle(),
                'formation_slug' => $formation->getSlug(),
                'formation_level' => $formation->getLevel(),
                'formation_duration_hours' => $formation->getDurationHours(),
                'formation_category' => $formation->getCategory()?->getName(),
                'formation_price' => $formation->getPrice(),
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'ip_address' => $this->container->get('request_stack')->getCurrentRequest()?->getClientIp(),
                'user_agent' => $this->container->get('request_stack')->getCurrentRequest()?->headers->get('User-Agent'),
                'timestamp' => new \DateTime(),
            ]);

            // Get student's enrollment for this formation
            $this->logger->debug('Retrieving student enrollments for formation access validation', [
                'student_id' => $student->getId(),
                'formation_id' => $formation->getId(),
            ]);

            $enrollments = $this->contentAccessService->getStudentEnrollments($student);
            
            $this->logger->debug('Student enrollments retrieved for formation access', [
                'student_id' => $student->getId(),
                'total_enrollments' => count($enrollments),
                'enrollment_formations' => array_map(fn($e) => [
                    'enrollment_id' => $e->getId(),
                    'formation_id' => $e->getFormation()?->getId(),
                    'formation_title' => $e->getFormation()?->getTitle(),
                    'status' => $e->getStatus(),
                    'enrolled_at' => $e->getEnrolledAt()?->format('Y-m-d H:i:s'),
                ], $enrollments),
            ]);

            $enrollment = null;
            
            foreach ($enrollments as $e) {
                if ($e->getFormation() && $e->getFormation()->getId() === $formation->getId()) {
                    $enrollment = $e;
                    $this->logger->info('Valid enrollment found for formation access', [
                        'enrollment_id' => $enrollment->getId(),
                        'formation_id' => $formation->getId(),
                        'student_id' => $student->getId(),
                        'enrollment_status' => $enrollment->getStatus(),
                        'enrolled_at' => $enrollment->getEnrolledAt()?->format('Y-m-d H:i:s'),
                    ]);
                    break;
                }
            }

            if (!$enrollment) {
                $this->logger->warning('No valid enrollment found for formation access', [
                    'student_id' => $student->getId(),
                    'formation_id' => $formation->getId(),
                    'available_formation_ids' => array_map(fn($e) => $e->getFormation()?->getId(), $enrollments),
                ]);
            }

            // Log formation structure details
            $modules = $formation->getModules();
            $this->logger->debug('Formation structure details', [
                'formation_id' => $formation->getId(),
                'modules_count' => $modules->count(),
                'modules_details' => array_map(fn($module) => [
                    'module_id' => $module->getId(),
                    'module_title' => $module->getTitle(),
                    'module_order' => $module->getOrderIndex(),
                    'module_duration_hours' => $module->getDurationHours(),
                    'chapters_count' => $module->getChapters()->count(),
                    'is_active' => $module->isActive(),
                ], $modules->toArray()),
            ]);

            $this->logger->info('Formation view successful', [
                'formation_id' => $formation->getId(),
                'student_id' => $student->getId(),
                'enrollment_found' => $enrollment !== null,
                'modules_count' => $modules->count(),
                'formation_is_active' => $formation->isActive(),
            ]);

            return $this->render('student/content/formation/view.html.twig', [
                'formation' => $formation,
                'enrollment' => $enrollment,
                'student' => $student,
                'page_title' => $formation->getTitle(),
            ]);

        } catch (\InvalidArgumentException $e) {
            $this->logger->error('Invalid argument provided for formation view', [
                'formation_id' => $formation->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $this->addFlash('error', 'Paramètres invalides pour l\'accès à la formation.');
            return $this->redirectToRoute('student_formation_index');

        } catch (\LogicException $e) {
            $this->logger->error('Logic error in formation view process', [
                'formation_id' => $formation->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $this->addFlash('error', 'Erreur dans la logique d\'accès à la formation.');
            return $this->redirectToRoute('student_formation_index');

        } catch (\Exception $e) {
            $this->logger->critical('Unexpected error during formation view', [
                'formation_id' => $formation->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_uri' => $this->container->get('request_stack')->getCurrentRequest()?->getRequestUri(),
                'request_method' => $this->container->get('request_stack')->getCurrentRequest()?->getMethod(),
            ]);
            
            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de l\'accès à la formation.');
            return $this->redirectToRoute('student_formation_index');
        }
    }
}
