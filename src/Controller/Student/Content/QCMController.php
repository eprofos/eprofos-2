<?php

declare(strict_types=1);

namespace App\Controller\Student\Content;

use App\Entity\Training\QCM;
use App\Entity\User\Student;
use App\Repository\Training\QCMRepository;
use App\Service\Security\ContentAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Student QCM Controller.
 *
 * Handles QCM content access and interaction for enrolled students
 * with proper access control and security checks.
 */
#[Route('/student/qcm')]
#[IsGranted('ROLE_STUDENT')]
class QCMController extends AbstractController
{
    public function __construct(
        private readonly QCMRepository $qcmRepository,
        private readonly ContentAccessService $contentAccessService
    ) {
    }

    /**
     * View a specific QCM with access control.
     */
    #[Route('/{id}', name: 'student_qcm_view', methods: ['GET'])]
    #[IsGranted('view', subject: 'qcm')]
    public function view(QCM $qcm): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        // Get student's enrollment for the formation containing this QCM
        $enrollments = $this->contentAccessService->getStudentEnrollments($student);
        $enrollment = null;
        
        foreach ($enrollments as $e) {
            if ($e->getFormation() && $e->getFormation()->getId() === $qcm->getCourse()?->getChapter()?->getModule()?->getFormation()?->getId()) {
                $enrollment = $e;
                break;
            }
        }

        return $this->render('student/content/qcm/view.html.twig', [
            'qcm' => $qcm,
            'course' => $qcm->getCourse(),
            'chapter' => $qcm->getCourse()?->getChapter(),
            'module' => $qcm->getCourse()?->getChapter()?->getModule(),
            'formation' => $qcm->getCourse()?->getChapter()?->getModule()?->getFormation(),
            'enrollment' => $enrollment,
            'student' => $student,
            'page_title' => $qcm->getTitle(),
        ]);
    }

    /**
     * Take/interact with a QCM.
     */
    #[Route('/{id}/take', name: 'student_qcm_take', methods: ['GET', 'POST'])]
    #[IsGranted('interact', subject: 'qcm')]
    public function take(QCM $qcm, Request $request): Response
    {
        /** @var Student $student */
        $student = $this->getUser();

        if ($request->isMethod('POST')) {
            // TODO: Implement QCM submission logic
            // This would include scoring, saving results, etc.
            
            $this->addFlash('success', 'QCM soumis avec succès!');
            return $this->redirectToRoute('student_qcm_view', ['id' => $qcm->getId()]);
        }

        return $this->render('student/content/qcm/take.html.twig', [
            'qcm' => $qcm,
            'student' => $student,
            'page_title' => 'QCM: ' . $qcm->getTitle(),
        ]);
    }
}
