<?php

namespace App\EventListener;

use App\Entity\Training\Chapter;
use App\Service\DurationCalculationService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Psr\Log\LoggerInterface;

/**
 * Entity listener for Chapter duration synchronization
 * DISABLED: Using DurationUpdateListener instead
 */
// #[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: Chapter::class)]
// #[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: Chapter::class)]
// #[AsEntityListener(event: Events::postRemove, method: 'postRemove', entity: Chapter::class)]
class ChapterListener
{
    public function __construct(
        private DurationCalculationService $durationService,
        private LoggerInterface $logger
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Chapter) {
            return;
        }

        $this->updateModuleDuration($entity, 'persist');
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Chapter) {
            return;
        }

        $this->updateModuleDuration($entity, 'update');
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Chapter) {
            return;
        }

        $this->updateModuleDuration($entity, 'remove');
    }

    private function updateModuleDuration(Chapter $chapter, string $operation): void
    {
        // Skip if we're in sync mode to prevent circular updates
        if ($this->durationService->isSyncMode()) {
            return;
        }
        
        try {
            $module = $chapter->getModule();
            
            if ($module) {
                // Update the parent module duration (which will cascade up)
                $this->durationService->updateEntityDuration($module);
                
                $this->logger->info('Chapter duration change propagated', [
                    'chapter_id' => $chapter->getId(),
                    'chapter_title' => $chapter->getTitle(),
                    'module_id' => $module->getId(),
                    'module_title' => $module->getTitle(),
                    'operation' => $operation
                ]);
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to update module duration from chapter', [
                'chapter_id' => $chapter->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }
}
