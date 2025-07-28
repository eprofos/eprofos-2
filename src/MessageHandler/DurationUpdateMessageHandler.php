<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\DurationUpdateMessage;
use App\Service\Training\DurationCalculationService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for asynchronous duration updates.
 */
#[AsMessageHandler]
class DurationUpdateMessageHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DurationCalculationService $durationService,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(DurationUpdateMessage $message): void
    {
        $this->logger->info('Processing duration update message', [
            'entity_class' => $message->getEntityClass(),
            'entity_id' => $message->getEntityId(),
            'operation' => $message->getOperation(),
            'context' => $message->getContext(),
        ]);

        try {
            // Find the entity
            $entity = $this->entityManager->getRepository($message->getEntityClass())
                ->find($message->getEntityId())
            ;

            if (!$entity) {
                $this->logger->warning('Entity not found for duration update', [
                    'entity_class' => $message->getEntityClass(),
                    'entity_id' => $message->getEntityId(),
                ]);

                return;
            }

            // Update the entity duration
            $this->durationService->updateEntityDuration($entity);

            // Persist changes
            $this->entityManager->flush();

            $this->logger->info('Duration update completed successfully', [
                'entity_class' => $message->getEntityClass(),
                'entity_id' => $message->getEntityId(),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to process duration update message', [
                'entity_class' => $message->getEntityClass(),
                'entity_id' => $message->getEntityId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to trigger retry mechanism
        }
    }
}
