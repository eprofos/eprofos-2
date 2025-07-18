<?php

namespace App\Controller\Admin;

use App\Service\DurationCalculationService;
use App\Entity\Formation;
use App\Entity\Module;
use App\Entity\Chapter;
use App\Entity\Course;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/duration', name: 'admin_duration_')]
#[IsGranted('ROLE_ADMIN')]
class DurationManagementController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DurationCalculationService $durationService
    ) {
    }

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        return $this->render('admin/duration/index.html.twig');
    }

    #[Route('/statistics', name: 'statistics')]
    public function statistics(): Response
    {
        $stats = [
            'formations' => $this->getEntityStats(Formation::class),
            'modules' => $this->getEntityStats(Module::class),
            'chapters' => $this->getEntityStats(Chapter::class),
            'courses' => $this->getEntityStats(Course::class),
        ];

        return $this->render('admin/duration/statistics.html.twig', [
            'stats' => $stats
        ]);
    }

    #[Route('/analyze/{entityType}', name: 'analyze')]
    public function analyze(string $entityType): Response
    {
        $entityClass = match ($entityType) {
            'formation' => Formation::class,
            'module' => Module::class,
            'chapter' => Chapter::class,
            'course' => Course::class,
            default => throw $this->createNotFoundException('Invalid entity type')
        };

        $entities = $this->entityManager->getRepository($entityClass)->findBy(['isActive' => true]);
        $results = [];

        foreach ($entities as $entity) {
            $stats = $this->durationService->getDurationStatistics($entity);
            $stats['entity'] = $entity;
            $results[] = $stats;
        }

        return $this->render('admin/duration/analyze.html.twig', [
            'entity_type' => $entityType,
            'results' => $results
        ]);
    }

    #[Route('/update/{entityType}/{entityId}', name: 'update', methods: ['POST'])]
    public function updateDuration(string $entityType, int $entityId): JsonResponse
    {
        $entityClass = match ($entityType) {
            'formation' => Formation::class,
            'module' => Module::class,
            'chapter' => Chapter::class,
            'course' => Course::class,
            default => throw $this->createNotFoundException('Invalid entity type')
        };

        $entity = $this->entityManager->getRepository($entityClass)->find($entityId);
        if (!$entity) {
            throw $this->createNotFoundException('Entity not found');
        }

        try {
            $this->durationService->updateEntityDuration($entity);
            $this->entityManager->flush();

            $stats = $this->durationService->getDurationStatistics($entity);

            return new JsonResponse([
                'success' => true,
                'message' => 'Duration updated successfully',
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error updating duration: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/sync-all', name: 'sync_all', methods: ['POST'])]
    public function syncAll(Request $request): JsonResponse
    {
        $entityType = $request->request->get('entity_type', 'all');
        $batchSize = (int) $request->request->get('batch_size', 50);

        try {
            $count = 0;
            
            if ($entityType === 'all' || $entityType === 'course') {
                $count += $this->syncEntities(Course::class, $batchSize);
            }
            
            if ($entityType === 'all' || $entityType === 'chapter') {
                $count += $this->syncEntities(Chapter::class, $batchSize);
            }
            
            if ($entityType === 'all' || $entityType === 'module') {
                $count += $this->syncEntities(Module::class, $batchSize);
            }
            
            if ($entityType === 'all' || $entityType === 'formation') {
                $count += $this->syncEntities(Formation::class, $batchSize);
            }

            return new JsonResponse([
                'success' => true,
                'message' => "Synchronized {$count} entities",
                'count' => $count
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error synchronizing durations: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/clear-cache', name: 'clear_cache', methods: ['POST'])]
    public function clearCache(): JsonResponse
    {
        try {
            $this->durationService->clearDurationCaches();

            return new JsonResponse([
                'success' => true,
                'message' => 'Duration caches cleared successfully'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error clearing caches: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getEntityStats(string $entityClass): array
    {
        $total = $this->entityManager->getRepository($entityClass)->count(['isActive' => true]);
        $inconsistencies = 0;

        $entities = $this->entityManager->getRepository($entityClass)->findBy(['isActive' => true]);
        foreach ($entities as $entity) {
            $stats = $this->durationService->getDurationStatistics($entity);
            if ($stats['needs_update'] ?? false) {
                $inconsistencies++;
            }
        }

        return [
            'total' => $total,
            'inconsistencies' => $inconsistencies,
            'percentage' => $total > 0 ? round(($inconsistencies / $total) * 100, 2) : 0
        ];
    }

    private function syncEntities(string $entityClass, int $batchSize): int
    {
        $entities = $this->entityManager->getRepository($entityClass)->findBy(['isActive' => true]);
        $count = 0;

        foreach (array_chunk($entities, $batchSize) as $batch) {
            $this->entityManager->beginTransaction();
            
            try {
                foreach ($batch as $entity) {
                    $this->durationService->updateEntityDuration($entity);
                    $count++;
                }
                
                $this->entityManager->flush();
                $this->entityManager->commit();
            } catch (\Exception $e) {
                $this->entityManager->rollback();
                throw $e;
            }
        }

        return $count;
    }
}
