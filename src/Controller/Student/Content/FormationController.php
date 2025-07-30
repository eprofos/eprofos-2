<?php

declare(strict_types=1);

namespace App\Controller\Student\Content;

use App\Entity\Training\Formation;
use App\Entity\User\Student;
use App\Repository\Training\FormationRepository;
use App\Service\Security\ContentAccessService;
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
        private readonly ContentAccessService $contentAccessService
    ) {
    }

    /**
     * List all accessible formations for the current student.
     */
    #[Route('/', name: 'student_formation_index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        // Get formations accessible to this student
        $accessibleFormations = $this->contentAccessService->getAccessibleFormations($student);

        return $this->render('student/content/formation/index.html.twig', [
            'formations' => $accessibleFormations,
            'student' => $student,
            'page_title' => 'Mes Formations',
        ]);
    }

    /**
     * View a specific formation with access control.
     */
    #[Route('/{id}', name: 'student_formation_view', methods: ['GET'])]
    #[IsGranted('view', subject: 'formation')]
    public function view(Formation $formation): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        // Get student's enrollment for this formation
        $enrollments = $this->contentAccessService->getStudentEnrollments($student);
        $enrollment = null;
        
        foreach ($enrollments as $e) {
            if ($e->getFormation() && $e->getFormation()->getId() === $formation->getId()) {
                $enrollment = $e;
                break;
            }
        }

        return $this->render('student/content/formation/view.html.twig', [
            'formation' => $formation,
            'enrollment' => $enrollment,
            'student' => $student,
            'page_title' => $formation->getTitle(),
        ]);
    }
}
