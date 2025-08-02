<?php

declare(strict_types=1);

namespace App\Controller\Admin\Training;

use App\Entity\Training\Course;
use App\Entity\Training\Exercise;
use App\Repository\Training\ExerciseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/exercise')]
class ExerciseController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
        private LoggerInterface $logger,
    ) {}

    #[Route('/', name: 'admin_exercise_index', methods: ['GET'])]
    public function index(ExerciseRepository $exerciseRepository, Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $queryBuilder = $exerciseRepository->createQueryBuilder('e')
            ->leftJoin('e.course', 'c')
            ->leftJoin('c.chapter', 'ch')
            ->leftJoin('ch.module', 'm')
            ->leftJoin('m.formation', 'f')
            ->addSelect('c', 'ch', 'm', 'f')
            ->orderBy('e.createdAt', 'DESC')
        ;

        // Create a separate count query to avoid grouping issues
        $countQueryBuilder = $exerciseRepository->createQueryBuilder('e')
            ->select('COUNT(e.id)')
        ;
        $totalExercises = $countQueryBuilder->getQuery()->getSingleScalarResult();

        $exercises = $queryBuilder->setFirstResult($offset)->setMaxResults($limit)->getQuery()->getResult();

        $totalPages = ceil($totalExercises / $limit);

        return $this->render('admin/exercise/index.html.twig', [
            'exercises' => $exercises,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_exercises' => $totalExercises,
        ]);
    }

    #[Route('/new', name: 'admin_exercise_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $exercise = new Exercise();
        $courses = $this->entityManager->getRepository(Course::class)->findBy(['isActive' => true]);

        if ($request->isMethod('POST')) {
            try {
                $this->logger->info('Starting exercise creation process', [
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                    'request_data_keys' => array_keys($request->request->all())
                ]);

                $data = $request->request->all();

                // Validate required fields
                $requiredFields = ['title', 'description', 'type', 'difficulty', 'instructions', 'course_id'];
                foreach ($requiredFields as $field) {
                    if (empty($data[$field])) {
                        $this->logger->warning('Missing required field during exercise creation', [
                            'field' => $field,
                            'user_id' => $this->getUser()?->getUserIdentifier()
                        ]);
                        throw new \InvalidArgumentException("Le champ '{$field}' est requis");
                    }
                }

                $this->logger->debug('Exercise creation data validation passed', [
                    'title' => $data['title'],
                    'type' => $data['type'],
                    'difficulty' => $data['difficulty'],
                    'course_id' => $data['course_id']
                ]);

                $exercise->setTitle($data['title']);
                $exercise->setSlug($this->slugger->slug($data['title'])->lower()->toString());
                $exercise->setDescription($data['description']);
                $exercise->setType($data['type']);
                $exercise->setDifficulty($data['difficulty']);

                // Enhanced logging for instructions processing
                $this->logger->info('Processing exercise instructions', [
                    'exercise_title' => $data['title'],
                    'instructions_length' => strlen($data['instructions']),
                    'instructions_preview' => substr($data['instructions'], 0, 100) . '...',
                    'user_id' => $this->getUser()?->getUserIdentifier()
                ]);

                try {
                    $exercise->setInstructions($data['instructions']);
                    $this->logger->debug('Instructions successfully set for exercise', [
                        'exercise_title' => $data['title'],
                        'instructions_final_length' => strlen($exercise->getInstructions())
                    ]);
                } catch (\Exception $e) {
                    $this->logger->error('Failed to set instructions for exercise', [
                        'exercise_title' => $data['title'],
                        'error_message' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'instructions_length' => strlen($data['instructions']),
                        'user_id' => $this->getUser()?->getUserIdentifier()
                    ]);
                    throw new \RuntimeException('Erreur lors de la sauvegarde des instructions: ' . $e->getMessage());
                }

                $exercise->setEstimatedDurationMinutes((int) $data['estimated_duration_minutes']);
                $exercise->setMaxPoints((int) $data['max_points']);
                $exercise->setPassingPoints((int) $data['passing_points']);
                $exercise->setOrderIndex((int) $data['order_index']);

                $course = $this->entityManager->getRepository(Course::class)->find($data['course_id']);
                if (!$course) {
                    $this->logger->error('Course not found during exercise creation', [
                        'course_id' => $data['course_id'],
                        'exercise_title' => $data['title'],
                        'user_id' => $this->getUser()?->getUserIdentifier()
                    ]);
                    throw new \InvalidArgumentException('Le cours spécifié n\'existe pas');
                }
                $exercise->setCourse($course);

                // Handle JSON arrays with detailed logging
                $this->logger->debug('Processing JSON array fields for exercise', [
                    'exercise_title' => $data['title'],
                    'has_expected_outcomes' => !empty($data['expected_outcomes']),
                    'has_evaluation_criteria' => !empty($data['evaluation_criteria']),
                    'has_resources' => !empty($data['resources']),
                    'has_success_criteria' => !empty($data['success_criteria'])
                ]);

                if (!empty($data['expected_outcomes'])) {
                    try {
                        $expectedOutcomes = array_filter(explode("\n", $data['expected_outcomes']));
                        $exercise->setExpectedOutcomes($expectedOutcomes);
                        $this->logger->debug('Expected outcomes processed successfully', [
                            'exercise_title' => $data['title'],
                            'outcomes_count' => count($expectedOutcomes)
                        ]);
                    } catch (\Exception $e) {
                        $this->logger->error('Failed to process expected outcomes', [
                            'exercise_title' => $data['title'],
                            'error_message' => $e->getMessage(),
                            'raw_data' => $data['expected_outcomes']
                        ]);
                        throw new \RuntimeException('Erreur lors du traitement des résultats attendus: ' . $e->getMessage());
                    }
                }

                if (!empty($data['evaluation_criteria'])) {
                    try {
                        $evaluationCriteria = array_filter(explode("\n", $data['evaluation_criteria']));
                        $exercise->setEvaluationCriteria($evaluationCriteria);
                        $this->logger->debug('Evaluation criteria processed successfully', [
                            'exercise_title' => $data['title'],
                            'criteria_count' => count($evaluationCriteria)
                        ]);
                    } catch (\Exception $e) {
                        $this->logger->error('Failed to process evaluation criteria', [
                            'exercise_title' => $data['title'],
                            'error_message' => $e->getMessage(),
                            'raw_data' => $data['evaluation_criteria']
                        ]);
                        throw new \RuntimeException('Erreur lors du traitement des critères d\'évaluation: ' . $e->getMessage());
                    }
                }

                if (!empty($data['resources'])) {
                    try {
                        $resources = array_filter(explode("\n", $data['resources']));
                        $exercise->setResources($resources);
                        $this->logger->debug('Resources processed successfully', [
                            'exercise_title' => $data['title'],
                            'resources_count' => count($resources)
                        ]);
                    } catch (\Exception $e) {
                        $this->logger->error('Failed to process resources', [
                            'exercise_title' => $data['title'],
                            'error_message' => $e->getMessage(),
                            'raw_data' => $data['resources']
                        ]);
                        throw new \RuntimeException('Erreur lors du traitement des ressources: ' . $e->getMessage());
                    }
                }

                if (!empty($data['success_criteria'])) {
                    try {
                        $successCriteria = array_filter(explode("\n", $data['success_criteria']));
                        $exercise->setSuccessCriteria($successCriteria);
                        $this->logger->debug('Success criteria processed successfully', [
                            'exercise_title' => $data['title'],
                            'criteria_count' => count($successCriteria)
                        ]);
                    } catch (\Exception $e) {
                        $this->logger->error('Failed to process success criteria', [
                            'exercise_title' => $data['title'],
                            'error_message' => $e->getMessage(),
                            'raw_data' => $data['success_criteria']
                        ]);
                        throw new \RuntimeException('Erreur lors du traitement des critères de succès: ' . $e->getMessage());
                    }
                }

                $exercise->setPrerequisites($data['prerequisites'] ?? null);
                $exercise->setIsActive(isset($data['is_active']));

                $this->logger->info('Persisting new exercise to database', [
                    'exercise_title' => $exercise->getTitle(),
                    'exercise_slug' => $exercise->getSlug(),
                    'course_id' => $exercise->getCourse()->getId(),
                    'user_id' => $this->getUser()?->getUserIdentifier()
                ]);

                $this->entityManager->persist($exercise);
                $this->entityManager->flush();

                $this->logger->info('Exercise created successfully', [
                    'exercise_id' => $exercise->getId(),
                    'exercise_title' => $exercise->getTitle(),
                    'course_id' => $exercise->getCourse()->getId(),
                    'user_id' => $this->getUser()?->getUserIdentifier()
                ]);

                $this->addFlash('success', 'Exercice créé avec succès.');

                return $this->redirectToRoute('admin_exercise_index');

            } catch (\InvalidArgumentException $e) {
                $this->logger->warning('Validation error during exercise creation', [
                    'error_message' => $e->getMessage(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                    'request_data' => $request->request->all()
                ]);
                $this->addFlash('error', $e->getMessage());
            } catch (\RuntimeException $e) {
                $this->logger->error('Runtime error during exercise creation', [
                    'error_message' => $e->getMessage(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                    'trace' => $e->getTraceAsString()
                ]);
                $this->addFlash('error', $e->getMessage());
            } catch (\Exception $e) {
                $this->logger->critical('Unexpected error during exercise creation', [
                    'error_message' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                $this->addFlash('error', 'Une erreur inattendue s\'est produite. Veuillez réessayer.');
            }
        }

        return $this->render('admin/exercise/new.html.twig', [
            'exercise' => $exercise,
            'courses' => $courses,
            'types' => Exercise::TYPES,
            'difficulties' => Exercise::DIFFICULTIES,
        ]);
    }

    #[Route('/{id}', name: 'admin_exercise_show', methods: ['GET'])]
    public function show(Exercise $exercise): Response
    {
        return $this->render('admin/exercise/show.html.twig', [
            'exercise' => $exercise,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_exercise_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Exercise $exercise): Response
    {
        $courses = $this->entityManager->getRepository(Course::class)->findBy(['isActive' => true]);

        if ($request->isMethod('POST')) {
            try {
                $this->logger->info('Starting exercise edit process', [
                    'exercise_id' => $exercise->getId(),
                    'exercise_title' => $exercise->getTitle(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                    'request_data_keys' => array_keys($request->request->all())
                ]);

                $data = $request->request->all();

                // Validate required fields
                $requiredFields = ['title', 'description', 'type', 'difficulty', 'instructions', 'course_id'];
                foreach ($requiredFields as $field) {
                    if (empty($data[$field])) {
                        $this->logger->warning('Missing required field during exercise edit', [
                            'field' => $field,
                            'exercise_id' => $exercise->getId(),
                            'user_id' => $this->getUser()?->getUserIdentifier()
                        ]);
                        throw new \InvalidArgumentException("Le champ '{$field}' est requis");
                    }
                }

                $this->logger->debug('Exercise edit data validation passed', [
                    'exercise_id' => $exercise->getId(),
                    'old_title' => $exercise->getTitle(),
                    'new_title' => $data['title'],
                    'type' => $data['type'],
                    'difficulty' => $data['difficulty'],
                    'course_id' => $data['course_id']
                ]);

                $exercise->setTitle($data['title']);
                $exercise->setSlug($this->slugger->slug($data['title'])->lower()->toString());
                $exercise->setDescription($data['description']);
                $exercise->setType($data['type']);
                $exercise->setDifficulty($data['difficulty']);

                // Enhanced logging for instructions processing
                $this->logger->info('Processing exercise instructions update', [
                    'exercise_id' => $exercise->getId(),
                    'exercise_title' => $data['title'],
                    'old_instructions_length' => strlen($exercise->getInstructions() ?? ''),
                    'new_instructions_length' => strlen($data['instructions']),
                    'instructions_preview' => substr($data['instructions'], 0, 100) . '...',
                    'user_id' => $this->getUser()?->getUserIdentifier()
                ]);

                try {
                    $exercise->setInstructions($data['instructions']);
                    $this->logger->debug('Instructions successfully updated for exercise', [
                        'exercise_id' => $exercise->getId(),
                        'exercise_title' => $data['title'],
                        'instructions_final_length' => strlen($exercise->getInstructions())
                    ]);
                } catch (\Exception $e) {
                    $this->logger->error('Failed to update instructions for exercise', [
                        'exercise_id' => $exercise->getId(),
                        'exercise_title' => $data['title'],
                        'error_message' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'new_instructions_length' => strlen($data['instructions']),
                        'user_id' => $this->getUser()?->getUserIdentifier()
                    ]);
                    throw new \RuntimeException('Erreur lors de la mise à jour des instructions: ' . $e->getMessage());
                }

                $exercise->setEstimatedDurationMinutes((int) $data['estimated_duration_minutes']);
                $exercise->setMaxPoints((int) $data['max_points']);
                $exercise->setPassingPoints((int) $data['passing_points']);
                $exercise->setOrderIndex((int) $data['order_index']);

                $course = $this->entityManager->getRepository(Course::class)->find($data['course_id']);
                if (!$course) {
                    $this->logger->error('Course not found during exercise edit', [
                        'course_id' => $data['course_id'],
                        'exercise_id' => $exercise->getId(),
                        'exercise_title' => $data['title'],
                        'user_id' => $this->getUser()?->getUserIdentifier()
                    ]);
                    throw new \InvalidArgumentException('Le cours spécifié n\'existe pas');
                }
                $exercise->setCourse($course);

                // Handle JSON arrays with detailed logging
                $this->logger->debug('Processing JSON array fields for exercise update', [
                    'exercise_id' => $exercise->getId(),
                    'exercise_title' => $data['title'],
                    'has_expected_outcomes' => !empty($data['expected_outcomes']),
                    'has_evaluation_criteria' => !empty($data['evaluation_criteria']),
                    'has_resources' => !empty($data['resources']),
                    'has_success_criteria' => !empty($data['success_criteria'])
                ]);

                if (!empty($data['expected_outcomes'])) {
                    try {
                        $expectedOutcomes = array_filter(explode("\n", $data['expected_outcomes']));
                        $exercise->setExpectedOutcomes($expectedOutcomes);
                        $this->logger->debug('Expected outcomes updated successfully', [
                            'exercise_id' => $exercise->getId(),
                            'exercise_title' => $data['title'],
                            'outcomes_count' => count($expectedOutcomes)
                        ]);
                    } catch (\Exception $e) {
                        $this->logger->error('Failed to update expected outcomes', [
                            'exercise_id' => $exercise->getId(),
                            'exercise_title' => $data['title'],
                            'error_message' => $e->getMessage(),
                            'raw_data' => $data['expected_outcomes']
                        ]);
                        throw new \RuntimeException('Erreur lors de la mise à jour des résultats attendus: ' . $e->getMessage());
                    }
                }

                if (!empty($data['evaluation_criteria'])) {
                    try {
                        $evaluationCriteria = array_filter(explode("\n", $data['evaluation_criteria']));
                        $exercise->setEvaluationCriteria($evaluationCriteria);
                        $this->logger->debug('Evaluation criteria updated successfully', [
                            'exercise_id' => $exercise->getId(),
                            'exercise_title' => $data['title'],
                            'criteria_count' => count($evaluationCriteria)
                        ]);
                    } catch (\Exception $e) {
                        $this->logger->error('Failed to update evaluation criteria', [
                            'exercise_id' => $exercise->getId(),
                            'exercise_title' => $data['title'],
                            'error_message' => $e->getMessage(),
                            'raw_data' => $data['evaluation_criteria']
                        ]);
                        throw new \RuntimeException('Erreur lors de la mise à jour des critères d\'évaluation: ' . $e->getMessage());
                    }
                }

                if (!empty($data['resources'])) {
                    try {
                        $resources = array_filter(explode("\n", $data['resources']));
                        $exercise->setResources($resources);
                        $this->logger->debug('Resources updated successfully', [
                            'exercise_id' => $exercise->getId(),
                            'exercise_title' => $data['title'],
                            'resources_count' => count($resources)
                        ]);
                    } catch (\Exception $e) {
                        $this->logger->error('Failed to update resources', [
                            'exercise_id' => $exercise->getId(),
                            'exercise_title' => $data['title'],
                            'error_message' => $e->getMessage(),
                            'raw_data' => $data['resources']
                        ]);
                        throw new \RuntimeException('Erreur lors de la mise à jour des ressources: ' . $e->getMessage());
                    }
                }

                if (!empty($data['success_criteria'])) {
                    try {
                        $successCriteria = array_filter(explode("\n", $data['success_criteria']));
                        $exercise->setSuccessCriteria($successCriteria);
                        $this->logger->debug('Success criteria updated successfully', [
                            'exercise_id' => $exercise->getId(),
                            'exercise_title' => $data['title'],
                            'criteria_count' => count($successCriteria)
                        ]);
                    } catch (\Exception $e) {
                        $this->logger->error('Failed to update success criteria', [
                            'exercise_id' => $exercise->getId(),
                            'exercise_title' => $data['title'],
                            'error_message' => $e->getMessage(),
                            'raw_data' => $data['success_criteria']
                        ]);
                        throw new \RuntimeException('Erreur lors de la mise à jour des critères de succès: ' . $e->getMessage());
                    }
                }

                $exercise->setPrerequisites($data['prerequisites'] ?? null);
                $exercise->setIsActive(isset($data['is_active']));

                $this->logger->info('Saving exercise updates to database', [
                    'exercise_id' => $exercise->getId(),
                    'exercise_title' => $exercise->getTitle(),
                    'exercise_slug' => $exercise->getSlug(),
                    'course_id' => $exercise->getCourse()->getId(),
                    'user_id' => $this->getUser()?->getUserIdentifier()
                ]);

                $this->entityManager->flush();

                $this->logger->info('Exercise updated successfully', [
                    'exercise_id' => $exercise->getId(),
                    'exercise_title' => $exercise->getTitle(),
                    'course_id' => $exercise->getCourse()->getId(),
                    'user_id' => $this->getUser()?->getUserIdentifier()
                ]);

                $this->addFlash('success', 'Exercice modifié avec succès.');

                return $this->redirectToRoute('admin_exercise_index');

            } catch (\InvalidArgumentException $e) {
                $this->logger->warning('Validation error during exercise edit', [
                    'exercise_id' => $exercise->getId(),
                    'error_message' => $e->getMessage(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                    'request_data' => $request->request->all()
                ]);
                $this->addFlash('error', $e->getMessage());
            } catch (\RuntimeException $e) {
                $this->logger->error('Runtime error during exercise edit', [
                    'exercise_id' => $exercise->getId(),
                    'error_message' => $e->getMessage(),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                    'trace' => $e->getTraceAsString()
                ]);
                $this->addFlash('error', $e->getMessage());
            } catch (\Exception $e) {
                $this->logger->critical('Unexpected error during exercise edit', [
                    'exercise_id' => $exercise->getId(),
                    'error_message' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'user_id' => $this->getUser()?->getUserIdentifier(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                $this->addFlash('error', 'Une erreur inattendue s\'est produite. Veuillez réessayer.');
            }
        }

        return $this->render('admin/exercise/edit.html.twig', [
            'exercise' => $exercise,
            'courses' => $courses,
            'types' => Exercise::TYPES,
            'difficulties' => Exercise::DIFFICULTIES,
        ]);
    }

    #[Route('/{id}', name: 'admin_exercise_delete', methods: ['POST'])]
    public function delete(Request $request, Exercise $exercise): Response
    {
        if ($this->isCsrfTokenValid('delete' . $exercise->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($exercise);
            $this->entityManager->flush();
            $this->addFlash('success', 'Exercice supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_exercise_index');
    }
}
