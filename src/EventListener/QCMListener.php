<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Training\QCM;
use App\Service\Training\DurationCalculationService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Exception;
use Psr\Log\LoggerInterface;

/**
 * Entity listener for QCM duration synchronization
 * DISABLED: Using DurationUpdateListener instead.
 */
// #[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: QCM::class)]
// #[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: QCM::class)]
// #[AsEntityListener(event: Events::postRemove, method: 'postRemove', entity: QCM::class)]
class QCMListener
{
    public function __construct(
        private DurationCalculationService $durationService,
        private LoggerInterface $logger,
    ) {}

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof QCM) {
            return;
        }

        $this->updateCourseDuration($entity, 'persist');
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof QCM) {
            return;
        }

        $this->updateCourseDuration($entity, 'update');
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof QCM) {
            return;
        }

        $this->updateCourseDuration($entity, 'remove');
    }

    private function updateCourseDuration(QCM $qcm, string $operation): void
    {
        // Skip if we're in sync mode to prevent circular updates
        if ($this->durationService->isSyncMode()) {
            return;
        }

        try {
            $course = $qcm->getCourse();

            if ($course) {
                // Update the parent course duration (which will cascade up)
                $this->durationService->updateEntityDuration($course);

                $this->logger->info('QCM duration change propagated', [
                    'qcm_id' => $qcm->getId(),
                    'qcm_title' => $qcm->getTitle(),
                    'course_id' => $course->getId(),
                    'course_title' => $course->getTitle(),
                    'operation' => $operation,
                ]);
            }
        } catch (Exception $e) {
            $this->logger->error('Failed to update course duration from QCM', [
                'qcm_id' => $qcm->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
