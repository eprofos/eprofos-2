<?php

declare(strict_types=1);

namespace App\Controller\Admin\Assessment;

use App\Entity\Training\Course;
use App\Entity\Training\QCM;
use App\Repository\Training\QCMRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/qcm')]
class QCMController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
    ) {}

    #[Route('/', name: 'admin_qcm_index', methods: ['GET'])]
    public function index(QCMRepository $qcmRepository, Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $queryBuilder = $qcmRepository->createQueryBuilder('q')
            ->leftJoin('q.course', 'c')
            ->leftJoin('c.chapter', 'ch')
            ->leftJoin('ch.module', 'm')
            ->leftJoin('m.formation', 'f')
            ->addSelect('c', 'ch', 'm', 'f')
            ->orderBy('q.createdAt', 'DESC')
        ;

        // Create a separate count query to avoid grouping issues
        $countQueryBuilder = $qcmRepository->createQueryBuilder('q')
            ->select('COUNT(q.id)')
        ;
        $totalQCMs = $countQueryBuilder->getQuery()->getSingleScalarResult();

        $qcms = $queryBuilder->setFirstResult($offset)->setMaxResults($limit)->getQuery()->getResult();

        $totalPages = ceil($totalQCMs / $limit);

        return $this->render('admin/qcm/index.html.twig', [
            'qcms' => $qcms,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_qcms' => $totalQCMs,
        ]);
    }

    #[Route('/new', name: 'admin_qcm_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $qcm = new QCM();
        $courses = $this->entityManager->getRepository(Course::class)->findBy(['isActive' => true]);

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $qcm->setTitle($data['title']);
            $qcm->setSlug((string)$this->slugger->slug($data['title'])->lower());
            $qcm->setDescription($data['description']);
            $qcm->setInstructions($data['instructions'] ?? null);
            $qcm->setTimeLimitMinutes($data['time_limit_minutes'] ? (int) $data['time_limit_minutes'] : null);
            $qcm->setMaxScore((int) $data['max_score']);
            $qcm->setPassingScore((int) $data['passing_score']);
            $qcm->setMaxAttempts((int) $data['max_attempts']);
            $qcm->setOrderIndex((int) $data['order_index']);

            $course = $this->entityManager->getRepository(Course::class)->find($data['course_id']);
            $qcm->setCourse($course);

            // Handle boolean fields
            $qcm->setShowCorrectAnswers(isset($data['show_correct_answers']));
            $qcm->setShowExplanations(isset($data['show_explanations']));
            $qcm->setRandomizeQuestions(isset($data['randomize_questions']));
            $qcm->setRandomizeAnswers(isset($data['randomize_answers']));
            $qcm->setIsActive(isset($data['is_active']));

            // Handle JSON arrays
            if (!empty($data['evaluation_criteria'])) {
                $qcm->setEvaluationCriteria(array_filter(explode("\n", $data['evaluation_criteria'])));
            }

            if (!empty($data['success_criteria'])) {
                $qcm->setSuccessCriteria(array_filter(explode("\n", $data['success_criteria'])));
            }

            // Handle questions (basic structure - will be enhanced with JavaScript)
            $questions = [];
            if (!empty($data['questions'])) {
                $questions = json_decode($data['questions'], true) ?? [];
            }
            $qcm->setQuestions($questions);

            $this->entityManager->persist($qcm);
            $this->entityManager->flush();

            $this->addFlash('success', 'QCM créé avec succès.');

            return $this->redirectToRoute('admin_qcm_index');
        }

        return $this->render('admin/qcm/new.html.twig', [
            'qcm' => $qcm,
            'courses' => $courses,
        ]);
    }

    #[Route('/{id}', name: 'admin_qcm_show', methods: ['GET'])]
    public function show(QCM $qcm): Response
    {
        return $this->render('admin/qcm/show.html.twig', [
            'qcm' => $qcm,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_qcm_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, QCM $qcm): Response
    {
        $courses = $this->entityManager->getRepository(Course::class)->findBy(['isActive' => true]);

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $qcm->setTitle($data['title']);
            $qcm->setSlug((string)$this->slugger->slug($data['title'])->lower());
            $qcm->setDescription($data['description']);
            $qcm->setInstructions($data['instructions'] ?? null);
            $qcm->setTimeLimitMinutes($data['time_limit_minutes'] ? (int) $data['time_limit_minutes'] : null);
            $qcm->setMaxScore((int) $data['max_score']);
            $qcm->setPassingScore((int) $data['passing_score']);
            $qcm->setMaxAttempts((int) $data['max_attempts']);
            $qcm->setOrderIndex((int) $data['order_index']);

            $course = $this->entityManager->getRepository(Course::class)->find($data['course_id']);
            $qcm->setCourse($course);

            // Handle boolean fields
            $qcm->setShowCorrectAnswers(isset($data['show_correct_answers']));
            $qcm->setShowExplanations(isset($data['show_explanations']));
            $qcm->setRandomizeQuestions(isset($data['randomize_questions']));
            $qcm->setRandomizeAnswers(isset($data['randomize_answers']));
            $qcm->setIsActive(isset($data['is_active']));

            // Handle JSON arrays
            if (!empty($data['evaluation_criteria'])) {
                $qcm->setEvaluationCriteria(array_filter(explode("\n", $data['evaluation_criteria'])));
            }

            if (!empty($data['success_criteria'])) {
                $qcm->setSuccessCriteria(array_filter(explode("\n", $data['success_criteria'])));
            }

            // Handle questions
            $questions = [];
            if (!empty($data['questions'])) {
                $questions = json_decode($data['questions'], true) ?? [];
            }
            $qcm->setQuestions($questions);

            $this->entityManager->flush();

            $this->addFlash('success', 'QCM modifié avec succès.');

            return $this->redirectToRoute('admin_qcm_index');
        }

        return $this->render('admin/qcm/edit.html.twig', [
            'qcm' => $qcm,
            'courses' => $courses,
        ]);
    }

    #[Route('/{id}', name: 'admin_qcm_delete', methods: ['POST'])]
    public function delete(Request $request, QCM $qcm): Response
    {
        if ($this->isCsrfTokenValid('delete' . $qcm->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($qcm);
            $this->entityManager->flush();
            $this->addFlash('success', 'QCM supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_qcm_index');
    }

    #[Route('/{id}/preview', name: 'admin_qcm_preview', methods: ['GET'])]
    public function preview(QCM $qcm): Response
    {
        return $this->render('admin/qcm/preview.html.twig', [
            'qcm' => $qcm,
        ]);
    }
}
