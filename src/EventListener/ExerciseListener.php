<?php

namespace App\EventListener;

use App\Entity\Training\Exercise;
use App\Service\DurationCalculationService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Psr\Log\LoggerInterface;

/**
 * Entity listener for Exercise duration synchronization
 * DISABLED: Using DurationUpdateListener instead
 */
// #[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: Exercise::class)]
// #[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: Exercise::class)]
// #[AsEntityListener(event: Events::postRemove, method: 'postRemove', entity: Exercise::class)]
class ExerciseListener
{
    public function __construct(
        private DurationCalculationService $durationService,
        private LoggerInterface $logger
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Exercise) {
            return;
        }

        $this->updateCourseDuration($entity, 'persist');
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Exercise) {
            return;
        }

        $this->updateCourseDuration($entity, 'update');
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Exercise) {
            return;
        }

        $this->updateCourseDuration($entity, 'remove');
    }

    private function updateCourseDuration(Exercise $exercise, string $operation): void
    {
        // Skip if we're in sync mode to prevent circular updates
        if ($this->durationService->isSyncMode()) {
            return;
        }
        
        try {
            $course = $exercise->getCourse();
            
            if ($course) {
                // Update the parent course duration (which will cascade up)
                $this->durationService->updateEntityDuration($course);
                
                $this->logger->info('Exercise duration change propagated', [
                    'exercise_id' => $exercise->getId(),
                    'exercise_title' => $exercise->getTitle(),
                    'course_id' => $course->getId(),
                    'course_title' => $course->getTitle(),
                    'operation' => $operation
                ]);
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to update course duration from exercise', [
                'exercise_id' => $exercise->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }
}
