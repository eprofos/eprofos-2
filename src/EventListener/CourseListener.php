<?php

namespace App\EventListener;

use App\Entity\Training\Course;
use App\Service\Training\DurationCalculationService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Psr\Log\LoggerInterface;

/**
 * Entity listener for Course duration synchronization
 * DISABLED: Using DurationUpdateListener instead
 */
// #[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: Course::class)]
// #[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: Course::class)]
// #[AsEntityListener(event: Events::postRemove, method: 'postRemove', entity: Course::class)]
class CourseListener
{
    private array $scheduledUpdates = [];

    public function __construct(
        private DurationCalculationService $durationService,
        private LoggerInterface $logger
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Course) {
            return;
        }

        $this->scheduleUpdate($entity, 'persist');
        $this->processScheduledUpdates();
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Course) {
            return;
        }

        $this->scheduleUpdate($entity, 'update');
        $this->processScheduledUpdates();
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Course) {
            return;
        }

        $this->scheduleUpdate($entity, 'remove');
        $this->processScheduledUpdates();
    }

    private function scheduleUpdate(Course $course, string $operation): void
    {
        $key = 'course_' . $course->getId();
        
        if (!isset($this->scheduledUpdates[$key])) {
            $this->scheduledUpdates[$key] = [
                'entity' => $course,
                'operation' => $operation,
                'timestamp' => time()
            ];
        }
    }

    private function processScheduledUpdates(): void
    {
        // Skip if we're in sync mode to prevent circular updates
        if ($this->durationService->isSyncMode()) {
            $this->scheduledUpdates = [];
            return;
        }
        
        foreach ($this->scheduledUpdates as $key => $update) {
            try {
                $course = $update['entity'];
                
                // Update the course duration and propagate to parent chapter
                $this->durationService->updateEntityDuration($course);
                
                $this->logger->info('Course duration updated', [
                    'course_id' => $course->getId(),
                    'course_title' => $course->getTitle(),
                    'operation' => $update['operation']
                ]);
                
            } catch (\Exception $e) {
                $this->logger->error('Failed to update course duration', [
                    'course_id' => $update['entity']->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Clear processed updates
        $this->scheduledUpdates = [];
    }
}
