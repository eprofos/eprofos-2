<?php

declare(strict_types=1);

namespace App\Controller\Student\Content;

use App\Entity\Training\Module;
use App\Entity\User\Student;
use App\Repository\Training\ModuleRepository;
use App\Service\Security\ContentAccessService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Student Module Controller.
 *
 * Handles module content access for enrolled students with proper
 * access control and security checks.
 */
#[Route('/student/module')]
#[IsGranted('ROLE_STUDENT')]
class ModuleController extends AbstractController
{
    public function __construct(
        private readonly ModuleRepository $moduleRepository,
        private readonly ContentAccessService $contentAccessService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * View a specific module with access control.
     */
    #[Route('/{id}', name: 'student_module_view', methods: ['GET'])]
    #[IsGranted('view', subject: 'module')]
    public function view(Module $module): Response
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student attempting to view module', [
                'module_id' => $module->getId(),
                'module_title' => $module->getTitle(),
                'module_order' => $module->getOrderIndex(),
                'module_duration_hours' => $module->getDurationHours(),
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'formation_id' => $module->getFormation()?->getId(),
                'formation_title' => $module->getFormation()?->getTitle(),
                'ip_address' => $this->container->get('request_stack')->getCurrentRequest()?->getClientIp(),
                'user_agent' => $this->container->get('request_stack')->getCurrentRequest()?->headers->get('User-Agent'),
                'timestamp' => new \DateTime(),
            ]);

            // Get student's enrollment for the formation containing this module
            $this->logger->debug('Retrieving student enrollments for module access validation', [
                'student_id' => $student->getId(),
                'module_id' => $module->getId(),
            ]);

            $enrollments = $this->contentAccessService->getStudentEnrollments($student);
            
            $this->logger->debug('Student enrollments retrieved for module access', [
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
            $targetFormationId = $module->getFormation()?->getId();
            
            foreach ($enrollments as $e) {
                if ($e->getFormation() && $e->getFormation()->getId() === $targetFormationId) {
                    $enrollment = $e;
                    $this->logger->info('Valid enrollment found for module access', [
                        'enrollment_id' => $enrollment->getId(),
                        'formation_id' => $targetFormationId,
                        'student_id' => $student->getId(),
                        'module_id' => $module->getId(),
                        'enrollment_status' => $enrollment->getStatus(),
                    ]);
                    break;
                }
            }

            if (!$enrollment) {
                $this->logger->warning('No valid enrollment found for module access', [
                    'student_id' => $student->getId(),
                    'module_id' => $module->getId(),
                    'target_formation_id' => $targetFormationId,
                    'available_formation_ids' => array_map(fn($e) => $e->getFormation()?->getId(), $enrollments),
                ]);
            }

            // Log module structure details
            $chapters = $module->getChapters();
            $this->logger->debug('Module structure details', [
                'module_id' => $module->getId(),
                'chapters_count' => $chapters->count(),
                'chapters_details' => array_map(fn($chapter) => [
                    'chapter_id' => $chapter->getId(),
                    'chapter_title' => $chapter->getTitle(),
                    'chapter_order' => $chapter->getOrderIndex(),
                    'chapter_duration_minutes' => $chapter->getDurationMinutes(),
                    'courses_count' => $chapter->getCourses()->count(),
                    'is_active' => $chapter->isActive(),
                ], $chapters->toArray()),
            ]);

            $this->logger->info('Module view successful', [
                'module_id' => $module->getId(),
                'student_id' => $student->getId(),
                'enrollment_found' => $enrollment !== null,
                'chapters_count' => $chapters->count(),
                'module_duration_hours' => $module->getDurationHours(),
                'module_order' => $module->getOrderIndex(),
            ]);

            return $this->render('student/content/module/view.html.twig', [
                'module' => $module,
                'formation' => $module->getFormation(),
                'enrollment' => $enrollment,
                'student' => $student,
                'page_title' => $module->getTitle(),
            ]);

        } catch (\InvalidArgumentException $e) {
            $this->logger->error('Invalid argument provided for module view', [
                'module_id' => $module->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $this->addFlash('error', 'Paramètres invalides pour l\'accès au module.');
            return $this->redirectToRoute('student_formation_index');

        } catch (\LogicException $e) {
            $this->logger->error('Logic error in module view process', [
                'module_id' => $module->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $this->addFlash('error', 'Erreur dans la logique d\'accès au module.');
            return $this->redirectToRoute('student_formation_index');

        } catch (\Exception $e) {
            $this->logger->critical('Unexpected error during module view', [
                'module_id' => $module->getId(),
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
            
            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de l\'accès au module.');
            return $this->redirectToRoute('student_formation_index');
        }
    }
}
