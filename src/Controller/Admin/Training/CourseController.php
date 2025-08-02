<?php

declare(strict_types=1);

namespace App\Controller\Admin\Training;

use App\Entity\Training\Chapter;
use App\Entity\Training\Course;
use App\Repository\Training\CourseRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\Pdf;
use Psr\Log\LoggerInterface;
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
        private Pdf $pdf,
        private LoggerInterface $logger,
    ) {}

    #[Route('/', name: 'admin_course_index', methods: ['GET'])]
    public function index(CourseRepository $courseRepository, Request $request): Response
    {
        $this->logger->info('Starting course index page', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'page' => $request->query->getInt('page', 1),
            'route' => 'admin_course_index'
        ]);

        try {
            $page = max(1, $request->query->getInt('page', 1));
            $limit = 10;
            $offset = ($page - 1) * $limit;

            $this->logger->debug('Course index pagination parameters', [
                'page' => $page,
                'limit' => $limit,
                'offset' => $offset
            ]);

            // First, get the total count without joins to avoid grouping issues
            $this->logger->debug('Fetching total course count');
            $totalCourses = $courseRepository->createQueryBuilder('c')
                ->select('COUNT(c.id)')
                ->getQuery()
                ->getSingleScalarResult()
            ;

            $this->logger->debug('Total courses found', ['total_courses' => $totalCourses]);

            // Then get the courses with all related data
            $this->logger->debug('Fetching paginated courses with related data');
            $courses = $courseRepository->createQueryBuilder('c')
                ->leftJoin('c.chapter', 'ch')
                ->leftJoin('ch.module', 'm')
                ->leftJoin('m.formation', 'f')
                ->addSelect('ch', 'm', 'f')
                ->orderBy('c.createdAt', 'DESC')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult()
            ;

            $totalPages = ceil($totalCourses / $limit);

            $this->logger->info('Course index page completed successfully', [
                'courses_count' => count($courses),
                'total_courses' => $totalCourses,
                'current_page' => $page,
                'total_pages' => $totalPages
            ]);

            return $this->render('admin/course/index.html.twig', [
                'courses' => $courses,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_courses' => $totalCourses,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in course index page', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'page' => $request->query->getInt('page', 1)
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement de la liste des cours.');

            return $this->render('admin/course/index.html.twig', [
                'courses' => [],
                'current_page' => 1,
                'total_pages' => 0,
                'total_courses' => 0,
            ]);
        }
    }

    #[Route('/new', name: 'admin_course_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->logger->info('Starting course creation', [
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'method' => $request->getMethod(),
            'route' => 'admin_course_new'
        ]);

        try {
            $course = new Course();
            
            $this->logger->debug('Fetching active chapters for course creation');
            $chapters = $this->entityManager->getRepository(Chapter::class)->findBy(['isActive' => true]);
            $this->logger->debug('Active chapters found', ['chapters_count' => count($chapters)]);

            if ($request->isMethod('POST')) {
                $this->logger->info('Processing course creation form submission');
                
                $data = $request->request->all();
                $this->logger->debug('Form data received', [
                    'title' => $data['title'] ?? 'NOT_PROVIDED',
                    'type' => $data['type'] ?? 'NOT_PROVIDED',
                    'chapter_id' => $data['chapter_id'] ?? 'NOT_PROVIDED',
                    'duration_minutes' => $data['duration_minutes'] ?? 'NOT_PROVIDED'
                ]);

                // Validate required fields
                if (empty($data['title'])) {
                    $this->logger->warning('Course creation failed: missing title');
                    $this->addFlash('error', 'Le titre du cours est requis.');
                    return $this->render('admin/course/new.html.twig', [
                        'course' => $course,
                        'chapters' => $chapters,
                        'types' => Course::TYPES,
                    ]);
                }

                if (empty($data['chapter_id'])) {
                    $this->logger->warning('Course creation failed: missing chapter');
                    $this->addFlash('error', 'Le chapitre est requis.');
                    return $this->render('admin/course/new.html.twig', [
                        'course' => $course,
                        'chapters' => $chapters,
                        'types' => Course::TYPES,
                    ]);
                }

                $this->logger->debug('Setting course basic properties');
                $course->setTitle($data['title']);
                $slug = $this->slugger->slug($data['title'])->lower()->toString();
                $course->setSlug($slug);
                $this->logger->debug('Generated slug for course', ['slug' => $slug]);
                
                $course->setDescription($data['description']);
                $course->setType($data['type']);
                $course->setContent($data['content'] ?? null);
                $course->setDurationMinutes((int) $data['duration_minutes']);
                $course->setOrderIndex((int) $data['order_index']);

                $this->logger->debug('Finding chapter for course', ['chapter_id' => $data['chapter_id']]);
                $chapter = $this->entityManager->getRepository(Chapter::class)->find($data['chapter_id']);
                
                if (!$chapter) {
                    $this->logger->error('Chapter not found for course creation', ['chapter_id' => $data['chapter_id']]);
                    $this->addFlash('error', 'Chapitre introuvable.');
                    return $this->render('admin/course/new.html.twig', [
                        'course' => $course,
                        'chapters' => $chapters,
                        'types' => Course::TYPES,
                    ]);
                }

                $course->setChapter($chapter);
                $this->logger->debug('Chapter assigned to course', [
                    'chapter_id' => $chapter->getId(),
                    'chapter_title' => $chapter->getTitle()
                ]);

                // Handle JSON arrays
                $this->logger->debug('Processing learning objectives');
                if (!empty($data['learning_objectives'])) {
                    $objectives = array_filter(explode("\n", $data['learning_objectives']));
                    $course->setLearningObjectives($objectives);
                    $this->logger->debug('Learning objectives set', ['objectives_count' => count($objectives)]);
                }

                $this->logger->debug('Processing learning outcomes');
                if (!empty($data['learning_outcomes'])) {
                    $outcomes = array_filter(explode("\n", $data['learning_outcomes']));
                    $course->setLearningOutcomes($outcomes);
                    $this->logger->debug('Learning outcomes set', ['outcomes_count' => count($outcomes)]);
                }

                $this->logger->debug('Processing resources');
                if (!empty($data['resources'])) {
                    $resources = array_filter(explode("\n", $data['resources']));
                    $course->setResources($resources);
                    $this->logger->debug('Resources set', ['resources_count' => count($resources)]);
                }

                $this->logger->debug('Processing success criteria');
                if (!empty($data['success_criteria'])) {
                    $criteria = array_filter(explode("\n", $data['success_criteria']));
                    $course->setSuccessCriteria($criteria);
                    $this->logger->debug('Success criteria set', ['criteria_count' => count($criteria)]);
                }

                $course->setContentOutline($data['content_outline'] ?? null);
                $course->setPrerequisites($data['prerequisites'] ?? null);
                $course->setTeachingMethods($data['teaching_methods'] ?? null);
                $course->setAssessmentMethods($data['assessment_methods'] ?? null);
                $course->setIsActive(isset($data['is_active']));

                $this->logger->info('Persisting new course to database', [
                    'course_title' => $course->getTitle(),
                    'course_slug' => $course->getSlug(),
                    'chapter_id' => $chapter->getId()
                ]);

                $this->entityManager->persist($course);
                $this->entityManager->flush();

                $this->logger->info('Course created successfully', [
                    'course_id' => $course->getId(),
                    'course_title' => $course->getTitle(),
                    'course_slug' => $course->getSlug(),
                    'user_id' => $this->getUser()?->getUserIdentifier()
                ]);

                $this->addFlash('success', 'Cours créé avec succès.');

                return $this->redirectToRoute('admin_course_index');
            }

            $this->logger->debug('Rendering course creation form');
            return $this->render('admin/course/new.html.twig', [
                'course' => $course,
                'chapters' => $chapters,
                'types' => Course::TYPES,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error during course creation', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'method' => $request->getMethod()
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la création du cours.');

            try {
                $chapters = $this->entityManager->getRepository(Chapter::class)->findBy(['isActive' => true]);
                return $this->render('admin/course/new.html.twig', [
                    'course' => new Course(),
                    'chapters' => $chapters,
                    'types' => Course::TYPES,
                ]);
            } catch (\Exception $renderException) {
                $this->logger->critical('Critical error: unable to render course creation form', [
                    'original_error' => $e->getMessage(),
                    'render_error' => $renderException->getMessage()
                ]);
                
                return $this->redirectToRoute('admin_course_index');
            }
        }
    }

    #[Route('/{id}', name: 'admin_course_show', methods: ['GET'])]
    public function show(Course $course): Response
    {
        $this->logger->info('Displaying course details', [
            'course_id' => $course->getId(),
            'course_title' => $course->getTitle(),
            'course_slug' => $course->getSlug(),
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'route' => 'admin_course_show'
        ]);

        try {
            $this->logger->debug('Course details loaded successfully', [
                'course_id' => $course->getId(),
                'chapter_id' => $course->getChapter()?->getId(),
                'module_id' => $course->getChapter()?->getModule()?->getId(),
                'formation_id' => $course->getChapter()?->getModule()?->getFormation()?->getId(),
                'duration_minutes' => $course->getDurationMinutes(),
                'type' => $course->getType(),
                'is_active' => $course->isActive()
            ]);

            return $this->render('admin/course/show.html.twig', [
                'course' => $course,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error displaying course details', [
                'course_id' => $course->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => $this->getUser()?->getUserIdentifier()
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'affichage du cours.');
            return $this->redirectToRoute('admin_course_index');
        }
    }

    #[Route('/{id}/pdf', name: 'admin_course_pdf', methods: ['GET'])]
    public function downloadPdf(Course $course): Response
    {
        $this->logger->info('Starting PDF generation for course', [
            'course_id' => $course->getId(),
            'course_title' => $course->getTitle(),
            'course_slug' => $course->getSlug(),
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'route' => 'admin_course_pdf'
        ]);

        try {
            $this->logger->debug('Rendering HTML template for PDF');
            $html = $this->renderView('admin/course/pdf.html.twig', [
                'course' => $course,
            ]);

            $this->logger->debug('HTML template rendered successfully', [
                'html_length' => strlen($html)
            ]);

            $filename = sprintf(
                'cours-%s-%s.pdf',
                $course->getSlug(),
                (new DateTime())->format('Y-m-d'),
            );

            $this->logger->debug('Generated PDF filename', ['filename' => $filename]);

            $pdfOptions = [
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
            ];

            $this->logger->debug('PDF generation options', $pdfOptions);

            $this->logger->info('Generating PDF from HTML');
            $pdfContent = $this->pdf->getOutputFromHtml($html, $pdfOptions);

            $this->logger->info('PDF generated successfully', [
                'course_id' => $course->getId(),
                'filename' => $filename,
                'pdf_size_bytes' => strlen($pdfContent),
                'user_id' => $this->getUser()?->getUserIdentifier()
            ]);

            return new PdfResponse(
                $pdfContent,
                $filename,
            );

        } catch (\Exception $e) {
            $this->logger->error('Error generating PDF for course', [
                'course_id' => $course->getId(),
                'course_title' => $course->getTitle(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier()
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la génération du PDF.');
            return $this->redirectToRoute('admin_course_show', ['id' => $course->getId()]);
        }
    }

    #[Route('/{id}/edit', name: 'admin_course_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Course $course): Response
    {
        $this->logger->info('Starting course edit', [
            'course_id' => $course->getId(),
            'course_title' => $course->getTitle(),
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'method' => $request->getMethod(),
            'route' => 'admin_course_edit'
        ]);

        try {
            $this->logger->debug('Fetching active chapters for course edit');
            $chapters = $this->entityManager->getRepository(Chapter::class)->findBy(['isActive' => true]);
            $this->logger->debug('Active chapters found', ['chapters_count' => count($chapters)]);

            if ($request->isMethod('POST')) {
                $this->logger->info('Processing course edit form submission', [
                    'course_id' => $course->getId(),
                    'original_title' => $course->getTitle()
                ]);

                // Store original values for comparison
                $originalData = [
                    'title' => $course->getTitle(),
                    'slug' => $course->getSlug(),
                    'type' => $course->getType(),
                    'chapter_id' => $course->getChapter()?->getId(),
                    'duration_minutes' => $course->getDurationMinutes(),
                    'is_active' => $course->isActive()
                ];

                $data = $request->request->all();
                $this->logger->debug('Form data received for edit', [
                    'title' => $data['title'] ?? 'NOT_PROVIDED',
                    'type' => $data['type'] ?? 'NOT_PROVIDED',
                    'chapter_id' => $data['chapter_id'] ?? 'NOT_PROVIDED',
                    'duration_minutes' => $data['duration_minutes'] ?? 'NOT_PROVIDED'
                ]);

                // Validate required fields
                if (empty($data['title'])) {
                    $this->logger->warning('Course edit failed: missing title', ['course_id' => $course->getId()]);
                    $this->addFlash('error', 'Le titre du cours est requis.');
                    return $this->render('admin/course/edit.html.twig', [
                        'course' => $course,
                        'chapters' => $chapters,
                        'types' => Course::TYPES,
                    ]);
                }

                if (empty($data['chapter_id'])) {
                    $this->logger->warning('Course edit failed: missing chapter', ['course_id' => $course->getId()]);
                    $this->addFlash('error', 'Le chapitre est requis.');
                    return $this->render('admin/course/edit.html.twig', [
                        'course' => $course,
                        'chapters' => $chapters,
                        'types' => Course::TYPES,
                    ]);
                }

                $this->logger->debug('Updating course basic properties');
                $course->setTitle($data['title']);
                $newSlug = $this->slugger->slug($data['title'])->lower()->toString();
                $course->setSlug($newSlug);
                
                if ($originalData['title'] !== $data['title']) {
                    $this->logger->info('Course title changed', [
                        'course_id' => $course->getId(),
                        'old_title' => $originalData['title'],
                        'new_title' => $data['title'],
                        'new_slug' => $newSlug
                    ]);
                }

                $course->setDescription($data['description']);
                $course->setType($data['type']);
                $course->setContent($data['content'] ?? null);
                $course->setDurationMinutes((int) $data['duration_minutes']);
                $course->setOrderIndex((int) $data['order_index']);

                $this->logger->debug('Finding chapter for course edit', ['chapter_id' => $data['chapter_id']]);
                $chapter = $this->entityManager->getRepository(Chapter::class)->find($data['chapter_id']);
                
                if (!$chapter) {
                    $this->logger->error('Chapter not found for course edit', [
                        'course_id' => $course->getId(),
                        'chapter_id' => $data['chapter_id']
                    ]);
                    $this->addFlash('error', 'Chapitre introuvable.');
                    return $this->render('admin/course/edit.html.twig', [
                        'course' => $course,
                        'chapters' => $chapters,
                        'types' => Course::TYPES,
                    ]);
                }

                if ($originalData['chapter_id'] !== (int) $data['chapter_id']) {
                    $this->logger->info('Course chapter changed', [
                        'course_id' => $course->getId(),
                        'old_chapter_id' => $originalData['chapter_id'],
                        'new_chapter_id' => $chapter->getId(),
                        'new_chapter_title' => $chapter->getTitle()
                    ]);
                }

                $course->setChapter($chapter);

                // Handle JSON arrays
                $this->logger->debug('Processing learning objectives for edit');
                if (!empty($data['learning_objectives'])) {
                    $objectives = array_filter(explode("\n", $data['learning_objectives']));
                    $course->setLearningObjectives($objectives);
                    $this->logger->debug('Learning objectives updated', ['objectives_count' => count($objectives)]);
                }

                $this->logger->debug('Processing learning outcomes for edit');
                if (!empty($data['learning_outcomes'])) {
                    $outcomes = array_filter(explode("\n", $data['learning_outcomes']));
                    $course->setLearningOutcomes($outcomes);
                    $this->logger->debug('Learning outcomes updated', ['outcomes_count' => count($outcomes)]);
                }

                $this->logger->debug('Processing resources for edit');
                if (!empty($data['resources'])) {
                    $resources = array_filter(explode("\n", $data['resources']));
                    $course->setResources($resources);
                    $this->logger->debug('Resources updated', ['resources_count' => count($resources)]);
                }

                $this->logger->debug('Processing success criteria for edit');
                if (!empty($data['success_criteria'])) {
                    $criteria = array_filter(explode("\n", $data['success_criteria']));
                    $course->setSuccessCriteria($criteria);
                    $this->logger->debug('Success criteria updated', ['criteria_count' => count($criteria)]);
                }

                $course->setContentOutline($data['content_outline'] ?? null);
                $course->setPrerequisites($data['prerequisites'] ?? null);
                $course->setTeachingMethods($data['teaching_methods'] ?? null);
                $course->setAssessmentMethods($data['assessment_methods'] ?? null);
                $isActive = isset($data['is_active']);
                $course->setIsActive($isActive);

                if ($originalData['is_active'] !== $isActive) {
                    $this->logger->info('Course active status changed', [
                        'course_id' => $course->getId(),
                        'old_status' => $originalData['is_active'] ? 'active' : 'inactive',
                        'new_status' => $isActive ? 'active' : 'inactive'
                    ]);
                }

                $this->logger->info('Saving course changes to database', [
                    'course_id' => $course->getId(),
                    'course_title' => $course->getTitle()
                ]);

                $this->entityManager->flush();

                $this->logger->info('Course updated successfully', [
                    'course_id' => $course->getId(),
                    'course_title' => $course->getTitle(),
                    'course_slug' => $course->getSlug(),
                    'user_id' => $this->getUser()?->getUserIdentifier()
                ]);

                $this->addFlash('success', 'Cours modifié avec succès.');

                return $this->redirectToRoute('admin_course_index');
            }

            $this->logger->debug('Rendering course edit form');
            return $this->render('admin/course/edit.html.twig', [
                'course' => $course,
                'chapters' => $chapters,
                'types' => Course::TYPES,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error during course edit', [
                'course_id' => $course->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier(),
                'method' => $request->getMethod()
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la modification du cours.');

            try {
                $chapters = $this->entityManager->getRepository(Chapter::class)->findBy(['isActive' => true]);
                return $this->render('admin/course/edit.html.twig', [
                    'course' => $course,
                    'chapters' => $chapters,
                    'types' => Course::TYPES,
                ]);
            } catch (\Exception $renderException) {
                $this->logger->critical('Critical error: unable to render course edit form', [
                    'course_id' => $course->getId(),
                    'original_error' => $e->getMessage(),
                    'render_error' => $renderException->getMessage()
                ]);
                
                return $this->redirectToRoute('admin_course_index');
            }
        }
    }

    #[Route('/{id}', name: 'admin_course_delete', methods: ['POST'])]
    public function delete(Request $request, Course $course): Response
    {
        $this->logger->info('Starting course deletion', [
            'course_id' => $course->getId(),
            'course_title' => $course->getTitle(),
            'course_slug' => $course->getSlug(),
            'user_id' => $this->getUser()?->getUserIdentifier(),
            'route' => 'admin_course_delete'
        ]);

        try {
            $courseData = [
                'id' => $course->getId(),
                'title' => $course->getTitle(),
                'slug' => $course->getSlug(),
                'chapter_id' => $course->getChapter()?->getId(),
                'chapter_title' => $course->getChapter()?->getTitle(),
                'module_id' => $course->getChapter()?->getModule()?->getId(),
                'formation_id' => $course->getChapter()?->getModule()?->getFormation()?->getId(),
                'type' => $course->getType(),
                'duration_minutes' => $course->getDurationMinutes(),
                'is_active' => $course->isActive()
            ];

            $tokenValue = $request->request->get('_token');
            $this->logger->debug('CSRF token validation', [
                'course_id' => $course->getId(),
                'token_provided' => !empty($tokenValue)
            ]);

            if ($this->isCsrfTokenValid('delete' . $course->getId(), $tokenValue)) {
                $this->logger->info('CSRF token valid, proceeding with course deletion', [
                    'course_id' => $course->getId(),
                    'course_data' => $courseData
                ]);

                // Check for related entities before deletion
                $relatedEntities = [];
                
                // Check for exercises
                $exercises = $course->getExercises();
                if ($exercises && count($exercises) > 0) {
                    $relatedEntities['exercises'] = count($exercises);
                    $this->logger->warning('Course has related exercises', [
                        'course_id' => $course->getId(),
                        'exercises_count' => count($exercises)
                    ]);
                }

                // Check for QCMs
                $qcms = $course->getQcms();
                if ($qcms && count($qcms) > 0) {
                    $relatedEntities['qcms'] = count($qcms);
                    $this->logger->warning('Course has related QCMs', [
                        'course_id' => $course->getId(),
                        'qcms_count' => count($qcms)
                    ]);
                }

                if (!empty($relatedEntities)) {
                    $this->logger->info('Course deletion will cascade to related entities', [
                        'course_id' => $course->getId(),
                        'related_entities' => $relatedEntities
                    ]);
                }

                $this->logger->info('Removing course from database', ['course_id' => $course->getId()]);
                $this->entityManager->remove($course);
                $this->entityManager->flush();

                $this->logger->info('Course deleted successfully', [
                    'deleted_course' => $courseData,
                    'related_entities_deleted' => $relatedEntities,
                    'user_id' => $this->getUser()?->getUserIdentifier()
                ]);

                $this->addFlash('success', 'Cours supprimé avec succès.');
            } else {
                $this->logger->warning('Course deletion failed: invalid CSRF token', [
                    'course_id' => $course->getId(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                    'token_provided' => !empty($tokenValue)
                ]);

                $this->addFlash('error', 'Token de sécurité invalide.');
            }

        } catch (\Exception $e) {
            $this->logger->error('Error during course deletion', [
                'course_id' => $course->getId(),
                'course_title' => $course->getTitle(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $this->getUser()?->getUserIdentifier()
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la suppression du cours.');
        }

        return $this->redirectToRoute('admin_course_index');
    }
}
