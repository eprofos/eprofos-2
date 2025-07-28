<?php

namespace App\EventListener;

use App\Entity\Training\Formation;
use App\Entity\Training\Module;
use App\Entity\Training\Chapter;
use App\Entity\Training\Course;
use App\Entity\Training\Exercise;
use App\Entity\Training\QCM;
use App\Service\Training\DurationCalculationService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

/**
 * Duration Update Event Listener
 * 
 * Handles duration calculation and propagation across the 4-level hierarchy
 * when entities are created, updated, or removed.
 */
class DurationUpdateListener
{
    // Track entities that need duration updates to avoid cascade issues
    private array $pendingUpdates = [];
    
    // Track if we're currently processing updates to prevent infinite loops
    private bool $isProcessing = false;
    
    public function __construct(
        private DurationCalculationService $durationService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Handle entity creation - schedule duration updates
     */
    #[AsEntityListener(event: Events::prePersist, method: 'prePersist')]
    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        
        if ($this->isDurationRelevantEntity($entity)) {
            $this->logger->debug('Scheduling duration update for new entity', [
                'entity_class' => get_class($entity),
                'entity_id' => method_exists($entity, 'getId') ? $entity->getId() : 'new'
            ]);
            
            $this->scheduleUpdate($entity, 'persist');
        }
    }

    /**
     * Handle entity updates - schedule duration updates
     */
    #[AsEntityListener(event: Events::preUpdate, method: 'preUpdate')]
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        
        if ($this->isDurationRelevantEntity($entity) && $this->hasDurationRelevantChanges($entity, $args)) {
            $this->logger->debug('Scheduling duration update for modified entity', [
                'entity_class' => get_class($entity),
                'entity_id' => method_exists($entity, 'getId') ? $entity->getId() : null,
                'changes' => $args->getEntityChangeSet()
            ]);
            
            $this->scheduleUpdate($entity, 'update');
        }
    }

    /**
     * Handle entity removal - schedule duration updates for parent entities
     */
    #[AsEntityListener(event: Events::preRemove, method: 'preRemove')]
    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        
        if ($this->isDurationRelevantEntity($entity)) {
            $this->logger->debug('Scheduling duration update for entity removal', [
                'entity_class' => get_class($entity),
                'entity_id' => method_exists($entity, 'getId') ? $entity->getId() : null
            ]);
            
            // Schedule parent entities for update since child is being removed
            $this->scheduleParentUpdates($entity);
        }
    }

    /**
     * Process all pending duration updates after entity persistence
     */
    #[AsEntityListener(event: Events::postPersist, method: 'postPersist')]
    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->processPendingUpdates();
    }

    /**
     * Process all pending duration updates after entity updates
     */
    #[AsEntityListener(event: Events::postUpdate, method: 'postUpdate')]
    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->processPendingUpdates();
    }

    /**
     * Process all pending duration updates after entity removal
     */
    #[AsEntityListener(event: Events::postRemove, method: 'postRemove')]
    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->processPendingUpdates();
    }

    /**
     * Check if an entity is relevant for duration calculations
     */
    private function isDurationRelevantEntity(object $entity): bool
    {
        return $entity instanceof Formation
            || $entity instanceof Module
            || $entity instanceof Chapter
            || $entity instanceof Course
            || $entity instanceof Exercise
            || $entity instanceof QCM;
    }

    /**
     * Check if the entity changes affect duration calculations
     */
    private function hasDurationRelevantChanges(object $entity, PreUpdateEventArgs $args): bool
    {
        $changeSet = $args->getEntityChangeSet();
        
        // Fields that affect duration calculations
        $durationFields = [
            'durationMinutes',
            'durationHours',
            'estimatedDurationMinutes',
            'timeLimitMinutes',
            'isActive',
            'orderIndex'
        ];
        
        foreach ($durationFields as $field) {
            if (isset($changeSet[$field])) {
                return true;
            }
        }
        
        // Check for relationship changes that might affect duration
        $relationshipFields = [
            'formation',
            'module',
            'chapter',
            'course'
        ];
        
        foreach ($relationshipFields as $field) {
            if (isset($changeSet[$field])) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Schedule an entity for duration update
     */
    private function scheduleUpdate(object $entity, string $operation): void
    {
        if ($this->isProcessing) {
            return; // Prevent infinite loops
        }
        
        $entityKey = $this->getEntityKey($entity);
        
        if (!isset($this->pendingUpdates[$entityKey])) {
            $this->pendingUpdates[$entityKey] = [
                'entity' => $entity,
                'operation' => $operation,
                'priority' => $this->getUpdatePriority($entity)
            ];
        }
    }

    /**
     * Schedule parent entities for update when a child is removed
     */
    private function scheduleParentUpdates(object $entity): void
    {
        $parents = $this->getParentEntities($entity);
        
        foreach ($parents as $parent) {
            $this->scheduleUpdate($parent, 'update');
        }
    }

    /**
     * Get parent entities that need duration updates
     */
    private function getParentEntities(object $entity): array
    {
        $parents = [];
        
        switch (get_class($entity)) {
            case Exercise::class:
            case QCM::class:
                if ($entity->getCourse()) {
                    $parents[] = $entity->getCourse();
                }
                break;
                
            case Course::class:
                if ($entity->getChapter()) {
                    $parents[] = $entity->getChapter();
                }
                break;
                
            case Chapter::class:
                if ($entity->getModule()) {
                    $parents[] = $entity->getModule();
                }
                break;
                
            case Module::class:
                if ($entity->getFormation()) {
                    $parents[] = $entity->getFormation();
                }
                break;
        }
        
        return $parents;
    }

    /**
     * Process all pending duration updates
     */
    private function processPendingUpdates(): void
    {
        if (empty($this->pendingUpdates) || $this->isProcessing) {
            return;
        }
        
        // Skip processing if we're in sync mode to prevent circular updates
        if ($this->durationService->isSyncMode()) {
            $this->pendingUpdates = [];
            return;
        }
        
        $this->isProcessing = true;
        
        try {
            // Sort updates by priority (highest first)
            uasort($this->pendingUpdates, function ($a, $b) {
                return $b['priority'] <=> $a['priority'];
            });
            
            $this->logger->debug('Processing duration updates', [
                'update_count' => count($this->pendingUpdates)
            ]);
            
            // Process updates in batches to avoid memory issues
            $updates = array_values($this->pendingUpdates);
            $this->pendingUpdates = [];
            
            foreach ($updates as $update) {
                try {
                    $this->durationService->updateEntityDuration($update['entity']);
                } catch (\Exception $e) {
                    $this->logger->error('Failed to update entity duration', [
                        'entity_class' => get_class($update['entity']),
                        'entity_id' => method_exists($update['entity'], 'getId') ? $update['entity']->getId() : null,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            $this->logger->debug('Completed duration updates', [
                'processed_count' => count($updates)
            ]);
            
        } finally {
            $this->isProcessing = false;
        }
    }

    /**
     * Get unique key for an entity
     */
    private function getEntityKey(object $entity): string
    {
        $id = method_exists($entity, 'getId') ? $entity->getId() : spl_object_id($entity);
        return get_class($entity) . '_' . $id;
    }

    /**
     * Get update priority for an entity (higher = processed first)
     */
    private function getUpdatePriority(object $entity): int
    {
        return match (get_class($entity)) {
            Exercise::class, QCM::class => 100,  // Highest priority (leaf nodes)
            Course::class => 80,
            Chapter::class => 60,
            Module::class => 40,
            Formation::class => 20,  // Lowest priority (root nodes)
            default => 0
        };
    }

    /**
     * Clear all pending updates (useful for testing)
     */
    public function clearPendingUpdates(): void
    {
        $this->pendingUpdates = [];
        $this->isProcessing = false;
    }

    /**
     * Get current pending updates count (useful for monitoring)
     */
    public function getPendingUpdatesCount(): int
    {
        return count($this->pendingUpdates);
    }
}
