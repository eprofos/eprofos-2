<?php

declare(strict_types=1);

namespace App\Controller\Student\Content;

use App\Entity\Training\Module;
use App\Entity\User\Student;
use App\Repository\Training\ModuleRepository;
use App\Service\Security\ContentAccessService;
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
        private readonly ContentAccessService $contentAccessService
    ) {
    }

    /**
     * View a specific module with access control.
     */
    #[Route('/{id}', name: 'student_module_view', methods: ['GET'])]
    #[IsGranted('view', subject: 'module')]
    public function view(Module $module): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        // Get student's enrollment for the formation containing this module
        $enrollments = $this->contentAccessService->getStudentEnrollments($student);
        $enrollment = null;
        
        foreach ($enrollments as $e) {
            if ($e->getFormation() && $e->getFormation()->getId() === $module->getFormation()?->getId()) {
                $enrollment = $e;
                break;
            }
        }

        return $this->render('student/content/module/view.html.twig', [
            'module' => $module,
            'formation' => $module->getFormation(),
            'enrollment' => $enrollment,
            'student' => $student,
            'page_title' => $module->getTitle(),
        ]);
    }
}
