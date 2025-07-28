<?php

namespace App\Controller\Admin;

use App\Entity\Training\Course;
use App\Entity\Training\Chapter;
use App\Repository\Training\CourseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\Pdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/course')]
class CourseController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
        private Pdf $pdf
    ) {}

    #[Route('/', name: 'admin_course_index', methods: ['GET'])]
    public function index(CourseRepository $courseRepository, Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;

        // First, get the total count without joins to avoid grouping issues
        $totalCourses = $courseRepository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Then get the courses with all related data
        $courses = $courseRepository->createQueryBuilder('c')
            ->leftJoin('c.chapter', 'ch')
            ->leftJoin('ch.module', 'm')
            ->leftJoin('m.formation', 'f')
            ->addSelect('ch', 'm', 'f')
            ->orderBy('c.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $totalPages = ceil($totalCourses / $limit);

        return $this->render('admin/course/index.html.twig', [
            'courses' => $courses,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_courses' => $totalCourses,
        ]);
    }

    #[Route('/new', name: 'admin_course_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $course = new Course();
        $chapters = $this->entityManager->getRepository(Chapter::class)->findBy(['isActive' => true]);

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            
            $course->setTitle($data['title']);
            $course->setSlug($this->slugger->slug($data['title'])->lower());
            $course->setDescription($data['description']);
            $course->setType($data['type']);
            $course->setContent($data['content'] ?? null);
            $course->setDurationMinutes((int) $data['duration_minutes']);
            $course->setOrderIndex((int) $data['order_index']);
            
            $chapter = $this->entityManager->getRepository(Chapter::class)->find($data['chapter_id']);
            $course->setChapter($chapter);
            
            // Handle JSON arrays
            if (!empty($data['learning_objectives'])) {
                $course->setLearningObjectives(array_filter(explode("\n", $data['learning_objectives'])));
            }
            
            if (!empty($data['learning_outcomes'])) {
                $course->setLearningOutcomes(array_filter(explode("\n", $data['learning_outcomes'])));
            }
            
            if (!empty($data['resources'])) {
                $course->setResources(array_filter(explode("\n", $data['resources'])));
            }
            
            if (!empty($data['success_criteria'])) {
                $course->setSuccessCriteria(array_filter(explode("\n", $data['success_criteria'])));
            }
            
            $course->setContentOutline($data['content_outline'] ?? null);
            $course->setPrerequisites($data['prerequisites'] ?? null);
            $course->setTeachingMethods($data['teaching_methods'] ?? null);
            $course->setAssessmentMethods($data['assessment_methods'] ?? null);
            $course->setIsActive(isset($data['is_active']));

            $this->entityManager->persist($course);
            $this->entityManager->flush();

            $this->addFlash('success', 'Cours créé avec succès.');
            return $this->redirectToRoute('admin_course_index');
        }

        return $this->render('admin/course/new.html.twig', [
            'course' => $course,
            'chapters' => $chapters,
            'types' => Course::TYPES,
        ]);
    }

    #[Route('/{id}', name: 'admin_course_show', methods: ['GET'])]
    public function show(Course $course): Response
    {
        return $this->render('admin/course/show.html.twig', [
            'course' => $course,
        ]);
    }

    #[Route('/{id}/pdf', name: 'admin_course_pdf', methods: ['GET'])]
    public function downloadPdf(Course $course): Response
    {
        $html = $this->renderView('admin/course/pdf.html.twig', [
            'course' => $course,
        ]);

        $filename = sprintf(
            'cours-%s-%s.pdf',
            $course->getSlug(),
            (new \DateTime())->format('Y-m-d')
        );

        return new PdfResponse(
            $this->pdf->getOutputFromHtml($html, [
                'page-size' => 'A4',
                'margin-top' => '20mm',
                'margin-right' => '20mm',
                'margin-bottom' => '20mm',
                'margin-left' => '20mm',
                'encoding' => 'UTF-8',
                'print-media-type' => true,
                'no-background' => false,
                'lowquality' => false,
                'enable-javascript' => false,
                'disable-smart-shrinking' => true,
            ]),
            $filename
        );
    }

    #[Route('/{id}/edit', name: 'admin_course_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Course $course): Response
    {
        $chapters = $this->entityManager->getRepository(Chapter::class)->findBy(['isActive' => true]);

        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            
            $course->setTitle($data['title']);
            $course->setSlug($this->slugger->slug($data['title'])->lower());
            $course->setDescription($data['description']);
            $course->setType($data['type']);
            $course->setContent($data['content'] ?? null);
            $course->setDurationMinutes((int) $data['duration_minutes']);
            $course->setOrderIndex((int) $data['order_index']);
            
            $chapter = $this->entityManager->getRepository(Chapter::class)->find($data['chapter_id']);
            $course->setChapter($chapter);
            
            // Handle JSON arrays
            if (!empty($data['learning_objectives'])) {
                $course->setLearningObjectives(array_filter(explode("\n", $data['learning_objectives'])));
            }
            
            if (!empty($data['learning_outcomes'])) {
                $course->setLearningOutcomes(array_filter(explode("\n", $data['learning_outcomes'])));
            }
            
            if (!empty($data['resources'])) {
                $course->setResources(array_filter(explode("\n", $data['resources'])));
            }
            
            if (!empty($data['success_criteria'])) {
                $course->setSuccessCriteria(array_filter(explode("\n", $data['success_criteria'])));
            }
            
            $course->setContentOutline($data['content_outline'] ?? null);
            $course->setPrerequisites($data['prerequisites'] ?? null);
            $course->setTeachingMethods($data['teaching_methods'] ?? null);
            $course->setAssessmentMethods($data['assessment_methods'] ?? null);
            $course->setIsActive(isset($data['is_active']));

            $this->entityManager->flush();

            $this->addFlash('success', 'Cours modifié avec succès.');
            return $this->redirectToRoute('admin_course_index');
        }

        return $this->render('admin/course/edit.html.twig', [
            'course' => $course,
            'chapters' => $chapters,
            'types' => Course::TYPES,
        ]);
    }

    #[Route('/{id}', name: 'admin_course_delete', methods: ['POST'])]
    public function delete(Request $request, Course $course): Response
    {
        if ($this->isCsrfTokenValid('delete'.$course->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($course);
            $this->entityManager->flush();
            $this->addFlash('success', 'Cours supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_course_index');
    }
}
