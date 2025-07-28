<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Training\Course;
use App\Entity\Training\Exercise;
use App\Repository\Training\ExerciseRepository;
use Doctrine\ORM\EntityManagerInterface;
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
            $data = $request->request->all();

            $exercise->setTitle($data['title']);
            $exercise->setSlug((string)$this->slugger->slug($data['title'])->lower());
            $exercise->setDescription($data['description']);
            $exercise->setType($data['type']);
            $exercise->setDifficulty($data['difficulty']);
            $exercise->setInstructions($data['instructions']);
            $exercise->setEstimatedDurationMinutes((int) $data['estimated_duration_minutes']);
            $exercise->setMaxPoints((int) $data['max_points']);
            $exercise->setPassingPoints((int) $data['passing_points']);
            $exercise->setOrderIndex((int) $data['order_index']);

            $course = $this->entityManager->getRepository(Course::class)->find($data['course_id']);
            $exercise->setCourse($course);

            // Handle JSON arrays
            if (!empty($data['expected_outcomes'])) {
                $exercise->setExpectedOutcomes(array_filter(explode("\n", $data['expected_outcomes'])));
            }

            if (!empty($data['evaluation_criteria'])) {
                $exercise->setEvaluationCriteria(array_filter(explode("\n", $data['evaluation_criteria'])));
            }

            if (!empty($data['resources'])) {
                $exercise->setResources(array_filter(explode("\n", $data['resources'])));
            }

            if (!empty($data['success_criteria'])) {
                $exercise->setSuccessCriteria(array_filter(explode("\n", $data['success_criteria'])));
            }

            $exercise->setPrerequisites($data['prerequisites'] ?? null);
            $exercise->setIsActive(isset($data['is_active']));

            $this->entityManager->persist($exercise);
            $this->entityManager->flush();

            $this->addFlash('success', 'Exercice créé avec succès.');

            return $this->redirectToRoute('admin_exercise_index');
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
            $data = $request->request->all();

            $exercise->setTitle($data['title']);
            $exercise->setSlug((string)$this->slugger->slug($data['title'])->lower());
            $exercise->setDescription($data['description']);
            $exercise->setType($data['type']);
            $exercise->setDifficulty($data['difficulty']);
            $exercise->setInstructions($data['instructions']);
            $exercise->setEstimatedDurationMinutes((int) $data['estimated_duration_minutes']);
            $exercise->setMaxPoints((int) $data['max_points']);
            $exercise->setPassingPoints((int) $data['passing_points']);
            $exercise->setOrderIndex((int) $data['order_index']);

            $course = $this->entityManager->getRepository(Course::class)->find($data['course_id']);
            $exercise->setCourse($course);

            // Handle JSON arrays
            if (!empty($data['expected_outcomes'])) {
                $exercise->setExpectedOutcomes(array_filter(explode("\n", $data['expected_outcomes'])));
            }

            if (!empty($data['evaluation_criteria'])) {
                $exercise->setEvaluationCriteria(array_filter(explode("\n", $data['evaluation_criteria'])));
            }

            if (!empty($data['resources'])) {
                $exercise->setResources(array_filter(explode("\n", $data['resources'])));
            }

            if (!empty($data['success_criteria'])) {
                $exercise->setSuccessCriteria(array_filter(explode("\n", $data['success_criteria'])));
            }

            $exercise->setPrerequisites($data['prerequisites'] ?? null);
            $exercise->setIsActive(isset($data['is_active']));

            $this->entityManager->flush();

            $this->addFlash('success', 'Exercice modifié avec succès.');

            return $this->redirectToRoute('admin_exercise_index');
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
