<?php

declare(strict_types=1);

namespace App\Controller\Admin\Assessment;

use App\Entity\Training\Course;
use App\Entity\Training\QCM;
use App\Repository\Training\QCMRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
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
        private LoggerInterface $logger,
    ) {}

    #[Route('/', name: 'admin_qcm_index', methods: ['GET'])]
    public function index(QCMRepository $qcmRepository, Request $request): Response
    {
        $userId = $this->getUser() ? $this->getUser()->getUserIdentifier() : null;
        $page = max(1, $request->query->getInt('page', 1));
        
        $this->logger->info('Accessing QCM index page', [
            'page' => $page,
            'user_id' => $userId,
        ]);

        try {
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

            $this->logger->info('Successfully loaded QCMs', [
                'total_qcms' => $totalQCMs,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'qcms_on_page' => count($qcms),
                'user_id' => $userId,
            ]);

            return $this->render('admin/qcm/index.html.twig', [
                'qcms' => $qcms,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_qcms' => $totalQCMs,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error loading QCM index page', [
                'page' => $page,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $userId,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des QCMs.');
            throw $e;
        }
    }

    #[Route('/new', name: 'admin_qcm_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $userId = $this->getUser() ? $this->getUser()->getUserIdentifier() : null;
        
        $this->logger->info('Accessing new QCM form', [
            'method' => $request->getMethod(),
            'user_id' => $userId,
        ]);

        try {
            $qcm = new QCM();
            $courses = $this->entityManager->getRepository(Course::class)->findBy(['isActive' => true]);

            $this->logger->debug('Loaded courses for QCM creation', [
                'courses_count' => count($courses),
                'user_id' => $userId,
            ]);

            if ($request->isMethod('POST')) {
                $this->logger->info('Processing new QCM form submission', [
                    'user_id' => $userId,
                ]);

                try {
                    $data = $request->request->all();

                    $this->logger->debug('Processing QCM form data', [
                        'title' => $data['title'] ?? '',
                        'course_id' => $data['course_id'] ?? null,
                        'max_score' => $data['max_score'] ?? 0,
                        'passing_score' => $data['passing_score'] ?? 0,
                        'user_id' => $userId,
                    ]);

                    $qcm->setTitle($data['title']);
                    $qcm->setSlug($this->slugger->slug($data['title'])->lower()->toString());
                    $qcm->setDescription($data['description']);
                    $qcm->setInstructions($data['instructions'] ?? null);
                    $qcm->setTimeLimitMinutes($data['time_limit_minutes'] ? (int) $data['time_limit_minutes'] : null);
                    $qcm->setMaxScore((int) $data['max_score']);
                    $qcm->setPassingScore((int) $data['passing_score']);
                    $qcm->setMaxAttempts((int) $data['max_attempts']);
                    $qcm->setOrderIndex((int) $data['order_index']);

                    $course = $this->entityManager->getRepository(Course::class)->find($data['course_id']);
                    if (!$course) {
                        $this->logger->warning('Course not found for QCM creation', [
                            'course_id' => $data['course_id'],
                            'user_id' => $userId,
                        ]);
                        throw new Exception('Course not found');
                    }
                    $qcm->setCourse($course);

                    // Handle boolean fields
                    $qcm->setShowCorrectAnswers(isset($data['show_correct_answers']));
                    $qcm->setShowExplanations(isset($data['show_explanations']));
                    $qcm->setRandomizeQuestions(isset($data['randomize_questions']));
                    $qcm->setRandomizeAnswers(isset($data['randomize_answers']));
                    $qcm->setIsActive(isset($data['is_active']));

                    // Handle JSON arrays
                    if (!empty($data['evaluation_criteria'])) {
                        $evaluationCriteria = array_filter(explode("\n", $data['evaluation_criteria']));
                        $qcm->setEvaluationCriteria($evaluationCriteria);
                        
                        $this->logger->debug('Set evaluation criteria', [
                            'criteria_count' => count($evaluationCriteria),
                            'user_id' => $userId,
                        ]);
                    }

                    if (!empty($data['success_criteria'])) {
                        $successCriteria = array_filter(explode("\n", $data['success_criteria']));
                        $qcm->setSuccessCriteria($successCriteria);
                        
                        $this->logger->debug('Set success criteria', [
                            'criteria_count' => count($successCriteria),
                            'user_id' => $userId,
                        ]);
                    }

                    // Handle questions (basic structure - will be enhanced with JavaScript)
                    $questions = [];
                    if (!empty($data['questions'])) {
                        $questions = json_decode($data['questions'], true) ?? [];
                        
                        $this->logger->debug('Processing QCM questions', [
                            'questions_provided' => !empty($data['questions']),
                            'questions_count' => count($questions),
                            'user_id' => $userId,
                        ]);
                    }
                    $qcm->setQuestions($questions);

                    $this->entityManager->persist($qcm);
                    $this->entityManager->flush();

                    $this->logger->info('QCM created successfully', [
                        'qcm_id' => $qcm->getId(),
                        'title' => $qcm->getTitle(),
                        'slug' => $qcm->getSlug(),
                        'course_id' => $course->getId(),
                        'questions_count' => count($questions),
                        'user_id' => $userId,
                    ]);

                    $this->addFlash('success', 'QCM créé avec succès.');

                    return $this->redirectToRoute('admin_qcm_index');
                } catch (Exception $e) {
                    $this->logger->error('Error creating QCM', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'request_data' => $request->request->all(),
                        'user_id' => $userId,
                    ]);

                    $this->addFlash('error', 'Une erreur est survenue lors de la création du QCM: ' . $e->getMessage());
                }
            }

            return $this->render('admin/qcm/new.html.twig', [
                'qcm' => $qcm,
                'courses' => $courses,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error in new QCM controller', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $userId,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'accès à la création de QCM.');
            throw $e;
        }
    }

    #[Route('/{id}', name: 'admin_qcm_show', methods: ['GET'])]
    public function show(QCM $qcm): Response
    {
        $userId = $this->getUser() ? $this->getUser()->getUserIdentifier() : null;
        
        $this->logger->info('Viewing QCM details', [
            'qcm_id' => $qcm->getId(),
            'qcm_title' => $qcm->getTitle(),
            'user_id' => $userId,
        ]);

        try {
            return $this->render('admin/qcm/show.html.twig', [
                'qcm' => $qcm,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error showing QCM details', [
                'qcm_id' => $qcm->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $userId,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'affichage du QCM.');
            throw $e;
        }
    }

    #[Route('/{id}/edit', name: 'admin_qcm_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, QCM $qcm): Response
    {
        $userId = $this->getUser() ? $this->getUser()->getUserIdentifier() : null;
        
        $this->logger->info('Accessing edit QCM form', [
            'qcm_id' => $qcm->getId(),
            'qcm_title' => $qcm->getTitle(),
            'method' => $request->getMethod(),
            'user_id' => $userId,
        ]);

        try {
            $courses = $this->entityManager->getRepository(Course::class)->findBy(['isActive' => true]);

            if ($request->isMethod('POST')) {
                $this->logger->info('Processing edit QCM form submission', [
                    'qcm_id' => $qcm->getId(),
                    'user_id' => $userId,
                ]);

                try {
                    $data = $request->request->all();

                    $originalData = [
                        'title' => $qcm->getTitle(),
                        'course_id' => $qcm->getCourse()?->getId(),
                        'max_score' => $qcm->getMaxScore(),
                        'passing_score' => $qcm->getPassingScore(),
                        'is_active' => $qcm->isActive(),
                        'questions_count' => count($qcm->getQuestions() ?? []),
                    ];

                    $qcm->setTitle($data['title']);
                    $qcm->setSlug($this->slugger->slug($data['title'])->lower()->toString());
                    $qcm->setDescription($data['description']);
                    $qcm->setInstructions($data['instructions'] ?? null);
                    $qcm->setTimeLimitMinutes($data['time_limit_minutes'] ? (int) $data['time_limit_minutes'] : null);
                    $qcm->setMaxScore((int) $data['max_score']);
                    $qcm->setPassingScore((int) $data['passing_score']);
                    $qcm->setMaxAttempts((int) $data['max_attempts']);
                    $qcm->setOrderIndex((int) $data['order_index']);

                    $course = $this->entityManager->getRepository(Course::class)->find($data['course_id']);
                    if (!$course) {
                        $this->logger->warning('Course not found for QCM edit', [
                            'qcm_id' => $qcm->getId(),
                            'course_id' => $data['course_id'],
                            'user_id' => $userId,
                        ]);
                        throw new Exception('Course not found');
                    }
                    $qcm->setCourse($course);

                    // Handle boolean fields
                    $qcm->setShowCorrectAnswers(isset($data['show_correct_answers']));
                    $qcm->setShowExplanations(isset($data['show_explanations']));
                    $qcm->setRandomizeQuestions(isset($data['randomize_questions']));
                    $qcm->setRandomizeAnswers(isset($data['randomize_answers']));
                    $qcm->setIsActive(isset($data['is_active']));

                    // Handle JSON arrays
                    if (!empty($data['evaluation_criteria'])) {
                        $evaluationCriteria = array_filter(explode("\n", $data['evaluation_criteria']));
                        $qcm->setEvaluationCriteria($evaluationCriteria);
                    }

                    if (!empty($data['success_criteria'])) {
                        $successCriteria = array_filter(explode("\n", $data['success_criteria']));
                        $qcm->setSuccessCriteria($successCriteria);
                    }

                    // Handle questions
                    $questions = [];
                    if (!empty($data['questions'])) {
                        $questions = json_decode($data['questions'], true) ?? [];
                    }
                    $qcm->setQuestions($questions);

                    $updatedData = [
                        'title' => $qcm->getTitle(),
                        'course_id' => $qcm->getCourse()?->getId(),
                        'max_score' => $qcm->getMaxScore(),
                        'passing_score' => $qcm->getPassingScore(),
                        'is_active' => $qcm->isActive(),
                        'questions_count' => count($qcm->getQuestions() ?? []),
                    ];

                    $this->entityManager->flush();

                    $this->logger->info('QCM updated successfully', [
                        'qcm_id' => $qcm->getId(),
                        'original_data' => $originalData,
                        'updated_data' => $updatedData,
                        'changes' => array_diff_assoc($updatedData, $originalData),
                        'user_id' => $userId,
                    ]);

                    $this->addFlash('success', 'QCM modifié avec succès.');

                    return $this->redirectToRoute('admin_qcm_index');
                } catch (Exception $e) {
                    $this->logger->error('Error updating QCM', [
                        'qcm_id' => $qcm->getId(),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'request_data' => $request->request->all(),
                        'user_id' => $userId,
                    ]);

                    $this->addFlash('error', 'Une erreur est survenue lors de la modification du QCM: ' . $e->getMessage());
                }
            }

            return $this->render('admin/qcm/edit.html.twig', [
                'qcm' => $qcm,
                'courses' => $courses,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error in edit QCM controller', [
                'qcm_id' => $qcm->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $userId,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'accès à la modification du QCM.');
            throw $e;
        }
    }

    #[Route('/{id}', name: 'admin_qcm_delete', methods: ['POST'])]
    public function delete(Request $request, QCM $qcm): Response
    {
        $userId = $this->getUser() ? $this->getUser()->getUserIdentifier() : null;
        
        $this->logger->info('Attempting to delete QCM', [
            'qcm_id' => $qcm->getId(),
            'qcm_title' => $qcm->getTitle(),
            'user_id' => $userId,
        ]);

        try {
            if ($this->isCsrfTokenValid('delete' . $qcm->getId(), $request->request->get('_token'))) {
                $qcmData = [
                    'id' => $qcm->getId(),
                    'title' => $qcm->getTitle(),
                    'slug' => $qcm->getSlug(),
                    'course_id' => $qcm->getCourse()?->getId(),
                    'max_score' => $qcm->getMaxScore(),
                    'questions_count' => count($qcm->getQuestions() ?? []),
                ];

                $this->entityManager->remove($qcm);
                $this->entityManager->flush();

                $this->logger->info('QCM deleted successfully', [
                    'deleted_qcm' => $qcmData,
                    'user_id' => $userId,
                ]);

                $this->addFlash('success', 'QCM supprimé avec succès.');
            } else {
                $this->logger->warning('Invalid CSRF token for QCM deletion', [
                    'qcm_id' => $qcm->getId(),
                    'provided_token' => $request->request->get('_token'),
                    'user_id' => $userId,
                ]);

                $this->addFlash('error', 'Token CSRF invalide.');
            }

            return $this->redirectToRoute('admin_qcm_index');
        } catch (Exception $e) {
            $this->logger->error('Error deleting QCM', [
                'qcm_id' => $qcm->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $userId,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la suppression du QCM: ' . $e->getMessage());
            return $this->redirectToRoute('admin_qcm_index');
        }
    }

    #[Route('/{id}/preview', name: 'admin_qcm_preview', methods: ['GET'])]
    public function preview(QCM $qcm): Response
    {
        $userId = $this->getUser() ? $this->getUser()->getUserIdentifier() : null;
        
        $this->logger->info('Previewing QCM', [
            'qcm_id' => $qcm->getId(),
            'qcm_title' => $qcm->getTitle(),
            'questions_count' => count($qcm->getQuestions() ?? []),
            'user_id' => $userId,
        ]);

        try {
            return $this->render('admin/qcm/preview.html.twig', [
                'qcm' => $qcm,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error previewing QCM', [
                'qcm_id' => $qcm->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $userId,
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la prévisualisation du QCM.');
            throw $e;
        }
    }
}
