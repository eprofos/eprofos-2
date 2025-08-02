<?php

declare(strict_types=1);

namespace App\Controller\Student\Content;

use App\Entity\Training\Chapter;
use App\Entity\User\Student;
use App\Repository\Training\ChapterRepository;
use App\Service\Security\ContentAccessService;
use DateTime;
use Exception;
use InvalidArgumentException;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Student Chapter Controller.
 *
 * Handles chapter content access for enrolled students with proper
 * access control and security checks.
 */
#[Route('/student/chapter')]
#[IsGranted('ROLE_STUDENT')]
class ChapterController extends AbstractController
{
    public function __construct(
        private readonly ChapterRepository $chapterRepository,
        private readonly ContentAccessService $contentAccessService,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * View a specific chapter with access control.
     */
    #[Route('/{id}', name: 'student_chapter_view', methods: ['GET'])]
    #[IsGranted('view', subject: 'chapter')]
    public function view(Chapter $chapter): Response
    {
        try {
            /** @var Student $student */
            $student = $this->getUser();

            $this->logger->info('Student attempting to view chapter', [
                'chapter_id' => $chapter->getId(),
                'chapter_title' => $chapter->getTitle(),
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'module_id' => $chapter->getModule()?->getId(),
                'formation_id' => $chapter->getModule()?->getFormation()?->getId(),
                'formation_title' => $chapter->getModule()?->getFormation()?->getTitle(),
                'ip_address' => $this->container->get('request_stack')->getCurrentRequest()?->getClientIp(),
                'user_agent' => $this->container->get('request_stack')->getCurrentRequest()?->headers->get('User-Agent'),
                'timestamp' => new DateTime(),
            ]);

            // Get student's enrollment for the formation containing this chapter
            $this->logger->debug('Retrieving student enrollments for chapter access validation', [
                'student_id' => $student->getId(),
                'chapter_id' => $chapter->getId(),
            ]);

            $enrollments = $this->contentAccessService->getStudentEnrollments($student);

            $this->logger->debug('Student enrollments retrieved', [
                'student_id' => $student->getId(),
                'total_enrollments' => count($enrollments),
                'enrollment_formations' => array_map(static fn ($e) => [
                    'enrollment_id' => $e->getId(),
                    'formation_id' => $e->getFormation()?->getId(),
                    'formation_title' => $e->getFormation()?->getTitle(),
                    'status' => $e->getStatus(),
                    'enrolled_at' => $e->getEnrolledAt()?->format('Y-m-d H:i:s'),
                ], $enrollments),
            ]);

            $enrollment = null;
            $targetFormationId = $chapter->getModule()?->getFormation()?->getId();

            foreach ($enrollments as $e) {
                if ($e->getFormation() && $e->getFormation()->getId() === $targetFormationId) {
                    $enrollment = $e;
                    $this->logger->info('Valid enrollment found for chapter access', [
                        'enrollment_id' => $enrollment->getId(),
                        'formation_id' => $targetFormationId,
                        'student_id' => $student->getId(),
                        'chapter_id' => $chapter->getId(),
                        'enrollment_status' => $enrollment->getStatus(),
                    ]);
                    break;
                }
            }

            if (!$enrollment) {
                $this->logger->warning('No valid enrollment found for chapter access', [
                    'student_id' => $student->getId(),
                    'chapter_id' => $chapter->getId(),
                    'target_formation_id' => $targetFormationId,
                    'available_formation_ids' => array_map(static fn ($e) => $e->getFormation()?->getId(), $enrollments),
                ]);
            }

            $this->logger->info('Chapter view successful', [
                'chapter_id' => $chapter->getId(),
                'student_id' => $student->getId(),
                'enrollment_found' => $enrollment !== null,
                'chapter_duration_minutes' => $chapter->getDurationMinutes(),
                'chapter_order' => $chapter->getOrderIndex(),
            ]);

            return $this->render('student/content/chapter/view.html.twig', [
                'chapter' => $chapter,
                'module' => $chapter->getModule(),
                'formation' => $chapter->getModule()?->getFormation(),
                'enrollment' => $enrollment,
                'student' => $student,
                'page_title' => $chapter->getTitle(),
            ]);
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Invalid argument provided for chapter view', [
                'chapter_id' => $chapter->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Paramètres invalides pour l\'accès au chapitre.');

            return $this->redirectToRoute('student_dashboard');
        } catch (LogicException $e) {
            $this->logger->error('Logic error in chapter view process', [
                'chapter_id' => $chapter->getId(),
                'student_id' => $this->getUser()?->getUserIdentifier(),
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Erreur dans la logique d\'accès au chapitre.');

            return $this->redirectToRoute('student_dashboard');
        } catch (Exception $e) {
            $this->logger->critical('Unexpected error during chapter view', [
                'chapter_id' => $chapter->getId(),
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

            $this->addFlash('error', 'Une erreur inattendue s\'est produite lors de l\'accès au chapitre.');

            return $this->redirectToRoute('student_dashboard');
        }
    }
}
