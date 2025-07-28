<?php

namespace App\EventListener;

use App\Entity\Training\Module;
use App\Service\Training\DurationCalculationService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Psr\Log\LoggerInterface;

/**
 * Entity listener for Module duration synchronization
 * DISABLED: Using DurationUpdateListener instead
 */
// #[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: Module::class)]
// #[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: Module::class)]
// #[AsEntityListener(event: Events::postRemove, method: 'postRemove', entity: Module::class)]
class ModuleListener
{
    public function __construct(
        private DurationCalculationService $durationService,
        private LoggerInterface $logger
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Module) {
            return;
        }

        $this->updateFormationDuration($entity, 'persist');
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Module) {
            return;
        }

        $this->updateFormationDuration($entity, 'update');
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Module) {
            return;
        }

        $this->updateFormationDuration($entity, 'remove');
    }

    private function updateFormationDuration(Module $module, string $operation): void
    {
        // Skip if we're in sync mode to prevent circular updates
        if ($this->durationService->isSyncMode()) {
            return;
        }
        
        try {
            $formation = $module->getFormation();
            
            if ($formation) {
                // Update the parent formation duration
                $this->durationService->updateEntityDuration($formation);
                
                $this->logger->info('Module duration change propagated', [
                    'module_id' => $module->getId(),
                    'module_title' => $module->getTitle(),
                    'formation_id' => $formation->getId(),
                    'formation_title' => $formation->getTitle(),
                    'operation' => $operation
                ]);
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to update formation duration from module', [
                'module_id' => $module->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }
}
