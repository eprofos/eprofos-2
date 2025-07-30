<?php

declare(strict_types=1);

namespace App\Controller\Student\Content;

use App\Entity\Training\Chapter;
use App\Entity\User\Student;
use App\Repository\Training\ChapterRepository;
use App\Service\Security\ContentAccessService;
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
        private readonly ContentAccessService $contentAccessService
    ) {
    }

    /**
     * View a specific chapter with access control.
     */
    #[Route('/{id}', name: 'student_chapter_view', methods: ['GET'])]
    #[IsGranted('view', subject: 'chapter')]
    public function view(Chapter $chapter): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        // Get student's enrollment for the formation containing this chapter
        $enrollments = $this->contentAccessService->getStudentEnrollments($student);
        $enrollment = null;
        
        foreach ($enrollments as $e) {
            if ($e->getFormation() && $e->getFormation()->getId() === $chapter->getModule()?->getFormation()?->getId()) {
                $enrollment = $e;
                break;
            }
        }

        return $this->render('student/content/chapter/view.html.twig', [
            'chapter' => $chapter,
            'module' => $chapter->getModule(),
            'formation' => $chapter->getModule()?->getFormation(),
            'enrollment' => $enrollment,
            'student' => $student,
            'page_title' => $chapter->getTitle(),
        ]);
    }
}
