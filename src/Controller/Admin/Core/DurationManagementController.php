<?php

declare(strict_types=1);

namespace App\Controller\Admin\Core;

use App\Entity\Training\Chapter;
use App\Entity\Training\Course;
use App\Entity\Training\Formation;
use App\Entity\Training\Module;
use App\Service\Training\DurationCalculationService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/duration')]
#[IsGranted('ROLE_ADMIN')]
class DurationManagementController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DurationCalculationService $durationService,
        private LoggerInterface $logger,
    ) {}

    #[Route('/', name: 'admin_duration_index')]
    public function index(): Response
    {
        try {
            $this->logger->info('Duration management index page accessed', [
                'user' => $this->getUser()?->getUserIdentifier(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            ]);

            return $this->render('admin/duration/index.html.twig');
        } catch (Exception $e) {
            $this->logger->error('Error accessing duration management index', [
                'user' => $this->getUser()?->getUserIdentifier(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'accès à la gestion des durées.');

            return $this->redirectToRoute('admin_dashboard');
        }
    }

    #[Route('/statistics', name: 'admin_duration_statistics')]
    public function statistics(): Response
    {
        try {
            $this->logger->info('Duration statistics page accessed', [
                'user' => $this->getUser()?->getUserIdentifier(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);

            $startTime = microtime(true);

            $stats = [
                'formations' => $this->getEntityStats(Formation::class),
                'modules' => $this->getEntityStats(Module::class),
                'chapters' => $this->getEntityStats(Chapter::class),
                'courses' => $this->getEntityStats(Course::class),
            ];

            $executionTime = microtime(true) - $startTime;

            $this->logger->info('Duration statistics calculated successfully', [
                'user' => $this->getUser()?->getUserIdentifier(),
                'execution_time' => $executionTime,
                'stats_summary' => [
                    'formations_total' => $stats['formations']['total'],
                    'formations_inconsistencies' => $stats['formations']['inconsistencies'],
                    'modules_total' => $stats['modules']['total'],
                    'modules_inconsistencies' => $stats['modules']['inconsistencies'],
                    'chapters_total' => $stats['chapters']['total'],
                    'chapters_inconsistencies' => $stats['chapters']['inconsistencies'],
                    'courses_total' => $stats['courses']['total'],
                    'courses_inconsistencies' => $stats['courses']['inconsistencies'],
                ],
            ]);

            return $this->render('admin/duration/statistics.html.twig', [
                'stats' => $stats,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error calculating duration statistics', [
                'user' => $this->getUser()?->getUserIdentifier(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du calcul des statistiques de durées.');

            return $this->redirectToRoute('admin_duration_index');
        }
    }

    #[Route('/analyze/{entityType}', name: 'admin_duration_analyze')]
    public function analyze(string $entityType): Response
    {
        try {
            $this->logger->info('Duration analysis started', [
                'user' => $this->getUser()?->getUserIdentifier(),
                'entity_type' => $entityType,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);

            $entityClass = match ($entityType) {
                'formation' => Formation::class,
                'module' => Module::class,
                'chapter' => Chapter::class,
                'course' => Course::class,
                default => throw $this->createNotFoundException('Invalid entity type: ' . $entityType)
            };

            $this->logger->debug('Entity class resolved', [
                'entity_type' => $entityType,
                'entity_class' => $entityClass,
            ]);

            $startTime = microtime(true);
            $entities = $this->entityManager->getRepository($entityClass)->findBy(['isActive' => true]);
            $entityCount = count($entities);

            $this->logger->info('Entities loaded for analysis', [
                'entity_type' => $entityType,
                'entity_count' => $entityCount,
                'load_time' => microtime(true) - $startTime,
            ]);

            $results = [];
            $analysisStartTime = microtime(true);

            foreach ($entities as $index => $entity) {
                try {
                    $entityStartTime = microtime(true);
                    $stats = $this->durationService->getDurationStatistics($entity);
                    $stats['entity'] = $entity;
                    $results[] = $stats;

                    $this->logger->debug('Entity analyzed', [
                        'entity_type' => $entityType,
                        'entity_id' => $entity->getId(),
                        'entity_title' => method_exists($entity, 'getTitle') ? $entity->getTitle() : 'N/A',
                        'analysis_time' => microtime(true) - $entityStartTime,
                        'needs_update' => $stats['needs_update'] ?? false,
                        'progress' => round(($index + 1) / $entityCount * 100, 2) . '%',
                    ]);
                } catch (Exception $e) {
                    $this->logger->warning('Failed to analyze entity', [
                        'entity_type' => $entityType,
                        'entity_id' => $entity->getId(),
                        'error' => $e->getMessage(),
                    ]);
                    // Continue with other entities
                }
            }

            $totalAnalysisTime = microtime(true) - $analysisStartTime;
            $entitiesNeedingUpdate = array_filter($results, fn($result) => $result['needs_update'] ?? false);

            $this->logger->info('Duration analysis completed successfully', [
                'user' => $this->getUser()?->getUserIdentifier(),
                'entity_type' => $entityType,
                'total_entities' => $entityCount,
                'entities_needing_update' => count($entitiesNeedingUpdate),
                'total_analysis_time' => $totalAnalysisTime,
                'avg_time_per_entity' => $entityCount > 0 ? $totalAnalysisTime / $entityCount : 0,
            ]);

            return $this->render('admin/duration/analyze.html.twig', [
                'entity_type' => $entityType,
                'results' => $results,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error during duration analysis', [
                'user' => $this->getUser()?->getUserIdentifier(),
                'entity_type' => $entityType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de l\'analyse des durées pour le type: ' . $entityType);

            return $this->redirectToRoute('admin_duration_statistics');
        }
    }

    #[Route('/update/{entityType}/{entityId}', name: 'admin_duration_update', methods: ['POST'])]
    public function updateDuration(string $entityType, int $entityId): JsonResponse
    {
        try {
            $this->logger->info('Duration update requested', [
                'user' => $this->getUser()?->getUserIdentifier(),
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);

            $entityClass = match ($entityType) {
                'formation' => Formation::class,
                'module' => Module::class,
                'chapter' => Chapter::class,
                'course' => Course::class,
                default => throw $this->createNotFoundException('Invalid entity type: ' . $entityType)
            };

            $this->logger->debug('Entity class resolved for update', [
                'entity_type' => $entityType,
                'entity_class' => $entityClass,
                'entity_id' => $entityId,
            ]);

            $entity = $this->entityManager->getRepository($entityClass)->find($entityId);
            if (!$entity) {
                $this->logger->warning('Entity not found for duration update', [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'user' => $this->getUser()?->getUserIdentifier(),
                ]);

                throw $this->createNotFoundException('Entity not found: ' . $entityType . ' #' . $entityId);
            }

            $this->logger->debug('Entity found for duration update', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'entity_title' => method_exists($entity, 'getTitle') ? $entity->getTitle() : 'N/A',
            ]);

            $startTime = microtime(true);

            // Get pre-update statistics for comparison
            $preUpdateStats = $this->durationService->getDurationStatistics($entity);
            $this->logger->debug('Pre-update statistics captured', [
                'entity_id' => $entityId,
                'pre_update_stats' => $preUpdateStats,
            ]);

            $this->durationService->updateEntityDuration($entity);
            $this->entityManager->flush();

            $updateTime = microtime(true) - $startTime;

            // Get post-update statistics
            $stats = $this->durationService->getDurationStatistics($entity);

            $this->logger->info('Duration updated successfully', [
                'user' => $this->getUser()?->getUserIdentifier(),
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'entity_title' => method_exists($entity, 'getTitle') ? $entity->getTitle() : 'N/A',
                'update_time' => $updateTime,
                'pre_update_duration' => $preUpdateStats['current_duration'] ?? null,
                'post_update_duration' => $stats['current_duration'] ?? null,
                'duration_changed' => ($preUpdateStats['current_duration'] ?? 0) !== ($stats['current_duration'] ?? 0),
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => 'Duration updated successfully',
                'stats' => $stats,
                'update_time' => $updateTime,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error updating entity duration', [
                'user' => $this->getUser()?->getUserIdentifier(),
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'success' => false,
                'message' => 'Error updating duration: ' . $e->getMessage(),
                'error_code' => 'DURATION_UPDATE_FAILED',
            ], 500);
        }
    }

    #[Route('/sync-all', name: 'admin_duration_sync_all', methods: ['POST'])]
    public function syncAll(Request $request): JsonResponse
    {
        $requestStartTime = microtime(true);

        try {
            $entityType = $request->request->get('entity_type', 'all');
            $batchSize = (int) $request->request->get('batch_size', 50);

            $this->logger->info('Bulk duration synchronization started', [
                'user' => $this->getUser()?->getUserIdentifier(),
                'entity_type' => $entityType,
                'batch_size' => $batchSize,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);

            // Validate batch size
            if ($batchSize < 1 || $batchSize > 1000) {
                $this->logger->warning('Invalid batch size provided', [
                    'provided_batch_size' => $batchSize,
                'user' => $this->getUser()?->getUserIdentifier(),
                ]);
                $batchSize = 50; // Default fallback
            }

            $count = 0;
            $errors = [];

            if ($entityType === 'all' || $entityType === 'course') {
                try {
                    $courseCount = $this->syncEntities(Course::class, $batchSize);
                    $count += $courseCount;
                    $this->logger->info('Courses synchronized', [
                        'count' => $courseCount,
                        'entity_type' => 'course',
                    ]);
                } catch (Exception $e) {
                    $errors[] = 'Courses: ' . $e->getMessage();
                    $this->logger->error('Error synchronizing courses', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            if ($entityType === 'all' || $entityType === 'chapter') {
                try {
                    $chapterCount = $this->syncEntities(Chapter::class, $batchSize);
                    $count += $chapterCount;
                    $this->logger->info('Chapters synchronized', [
                        'count' => $chapterCount,
                        'entity_type' => 'chapter',
                    ]);
                } catch (Exception $e) {
                    $errors[] = 'Chapters: ' . $e->getMessage();
                    $this->logger->error('Error synchronizing chapters', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            if ($entityType === 'all' || $entityType === 'module') {
                try {
                    $moduleCount = $this->syncEntities(Module::class, $batchSize);
                    $count += $moduleCount;
                    $this->logger->info('Modules synchronized', [
                        'count' => $moduleCount,
                        'entity_type' => 'module',
                    ]);
                } catch (Exception $e) {
                    $errors[] = 'Modules: ' . $e->getMessage();
                    $this->logger->error('Error synchronizing modules', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            if ($entityType === 'all' || $entityType === 'formation') {
                try {
                    $formationCount = $this->syncEntities(Formation::class, $batchSize);
                    $count += $formationCount;
                    $this->logger->info('Formations synchronized', [
                        'count' => $formationCount,
                        'entity_type' => 'formation',
                    ]);
                } catch (Exception $e) {
                    $errors[] = 'Formations: ' . $e->getMessage();
                    $this->logger->error('Error synchronizing formations', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            $totalTime = microtime(true) - $requestStartTime;

            if (!empty($errors)) {
                $this->logger->warning('Bulk synchronization completed with errors', [
                    'user' => $this->getUser()?->getUserIdentifier(),
                    'total_synchronized' => $count,
                    'errors' => $errors,
                    'total_time' => $totalTime,
                ]);

                return new JsonResponse([
                    'success' => false,
                    'message' => "Synchronized {$count} entities with errors",
                    'count' => $count,
                    'errors' => $errors,
                    'total_time' => $totalTime,
                ], 206); // Partial Content
            }

            $this->logger->info('Bulk duration synchronization completed successfully', [
                'user' => $this->getUser()?->getUserIdentifier(),
                'entity_type' => $entityType,
                'total_synchronized' => $count,
                'total_time' => $totalTime,
                'avg_time_per_entity' => $count > 0 ? $totalTime / $count : 0,
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => "Synchronized {$count} entities",
                'count' => $count,
                'total_time' => $totalTime,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Critical error during bulk duration synchronization', [
                'user' => $this->getUser()?->getUserIdentifier(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_params' => $request->request->all(),
            ]);

            return new JsonResponse([
                'success' => false,
                'message' => 'Critical error during synchronization: ' . $e->getMessage(),
                'error_code' => 'SYNC_CRITICAL_ERROR',
            ], 500);
        }
    }

    #[Route('/clear-cache', name: 'admin_duration_clear_cache', methods: ['POST'])]
    public function clearCache(): JsonResponse
    {
        try {
            $this->logger->info('Duration cache clear requested', [
                'user' => $this->getUser()?->getUserIdentifier(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);

            $startTime = microtime(true);
            $this->durationService->clearDurationCaches();
            $clearTime = microtime(true) - $startTime;

            $this->logger->info('Duration caches cleared successfully', [
                'user' => $this->getUser()?->getUserIdentifier(),
                'clear_time' => $clearTime,
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => 'Duration caches cleared successfully',
                'clear_time' => $clearTime,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error clearing duration caches', [
                'user' => $this->getUser()?->getUserIdentifier(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new JsonResponse([
                'success' => false,
                'message' => 'Error clearing caches: ' . $e->getMessage(),
                'error_code' => 'CACHE_CLEAR_FAILED',
            ], 500);
        }
    }

    private function getEntityStats(string $entityClass): array
    {
        try {
            $this->logger->debug('Calculating entity statistics', [
                'entity_class' => $entityClass,
            ]);

            $startTime = microtime(true);
            $total = $this->entityManager->getRepository($entityClass)->count(['isActive' => true]);
            $countTime = microtime(true) - $startTime;

            $this->logger->debug('Entity count retrieved', [
                'entity_class' => $entityClass,
                'total_count' => $total,
                'count_time' => $countTime,
            ]);

            $inconsistencies = 0;
            $analysisStartTime = microtime(true);

            $entities = $this->entityManager->getRepository($entityClass)->findBy(['isActive' => true]);
            foreach ($entities as $entity) {
                try {
                    $stats = $this->durationService->getDurationStatistics($entity);
                    if ($stats['needs_update'] ?? false) {
                        $inconsistencies++;
                    }
                } catch (Exception $e) {
                    $this->logger->warning('Failed to get duration statistics for entity', [
                        'entity_class' => $entityClass,
                        'entity_id' => $entity->getId(),
                        'error' => $e->getMessage(),
                    ]);
                    // Count as inconsistency since we couldn't analyze it
                    $inconsistencies++;
                }
            }

            $analysisTime = microtime(true) - $analysisStartTime;
            $percentage = $total > 0 ? round(($inconsistencies / $total) * 100, 2) : 0;

            $this->logger->debug('Entity statistics calculated', [
                'entity_class' => $entityClass,
                'total' => $total,
                'inconsistencies' => $inconsistencies,
                'percentage' => $percentage,
                'analysis_time' => $analysisTime,
            ]);

            return [
                'total' => $total,
                'inconsistencies' => $inconsistencies,
                'percentage' => $percentage,
            ];
        } catch (Exception $e) {
            $this->logger->error('Error calculating entity statistics', [
                'entity_class' => $entityClass,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return empty stats to prevent breaking the statistics page
            return [
                'total' => 0,
                'inconsistencies' => 0,
                'percentage' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function syncEntities(string $entityClass, int $batchSize): int
    {
        try {
            $this->logger->info('Starting entity synchronization', [
                'entity_class' => $entityClass,
                'batch_size' => $batchSize,
            ]);

            $startTime = microtime(true);
            $entities = $this->entityManager->getRepository($entityClass)->findBy(['isActive' => true]);
            $totalEntities = count($entities);
            $loadTime = microtime(true) - $startTime;

            $this->logger->info('Entities loaded for synchronization', [
                'entity_class' => $entityClass,
                'total_entities' => $totalEntities,
                'load_time' => $loadTime,
            ]);

            $count = 0;
            $errors = 0;
            $batches = array_chunk($entities, $batchSize);
            $totalBatches = count($batches);

            foreach ($batches as $batchIndex => $batch) {
                $batchStartTime = microtime(true);

                $this->entityManager->beginTransaction();

                try {
                    $this->logger->debug('Processing batch', [
                        'entity_class' => $entityClass,
                        'batch_index' => $batchIndex + 1,
                        'total_batches' => $totalBatches,
                        'batch_size' => count($batch),
                    ]);

                    foreach ($batch as $entity) {
                        try {
                            $this->durationService->updateEntityDuration($entity);
                            $count++;
                        } catch (Exception $e) {
                            $errors++;
                            $this->logger->warning('Failed to update entity duration in batch', [
                                'entity_class' => $entityClass,
                                'entity_id' => $entity->getId(),
                                'batch_index' => $batchIndex + 1,
                                'error' => $e->getMessage(),
                            ]);
                            // Continue with other entities in the batch
                        }
                    }

                    $this->entityManager->flush();
                    $this->entityManager->commit();

                    $batchTime = microtime(true) - $batchStartTime;
                    $this->logger->debug('Batch processed successfully', [
                        'entity_class' => $entityClass,
                        'batch_index' => $batchIndex + 1,
                        'batch_time' => $batchTime,
                        'entities_in_batch' => count($batch),
                        'errors_in_batch' => $errors,
                    ]);
                } catch (Exception $e) {
                    $this->entityManager->rollback();
                    $this->logger->error('Batch processing failed, transaction rolled back', [
                        'entity_class' => $entityClass,
                        'batch_index' => $batchIndex + 1,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    throw $e;
                }
            }

            $totalSyncTime = microtime(true) - $startTime;

            $this->logger->info('Entity synchronization completed', [
                'entity_class' => $entityClass,
                'total_entities' => $totalEntities,
                'successfully_synced' => $count,
                'errors' => $errors,
                'total_time' => $totalSyncTime,
                'avg_time_per_entity' => $count > 0 ? $totalSyncTime / $count : 0,
            ]);

            return $count;
        } catch (Exception $e) {
            $this->logger->error('Critical error during entity synchronization', [
                'entity_class' => $entityClass,
                'batch_size' => $batchSize,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
