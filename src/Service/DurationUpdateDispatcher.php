<?php

namespace App\Service;

use App\Message\DurationUpdateMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Service for dispatching duration update messages
 */
class DurationUpdateDispatcher
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Dispatch a duration update message for an entity
     */
    public function dispatchUpdate(object $entity, string $operation = 'update', array $context = []): void
    {
        if (!method_exists($entity, 'getId') || !$entity->getId()) {
            $this->logger->warning('Cannot dispatch duration update for entity without ID', [
                'entity_class' => get_class($entity),
                'operation' => $operation
            ]);
            return;
        }

        $message = new DurationUpdateMessage(
            get_class($entity),
            $entity->getId(),
            $operation,
            $context
        );

        $this->messageBus->dispatch($message);

        $this->logger->debug('Dispatched duration update message', [
            'entity_class' => get_class($entity),
            'entity_id' => $entity->getId(),
            'operation' => $operation
        ]);
    }

    /**
     * Dispatch duration update messages for multiple entities
     */
    public function dispatchBatchUpdate(array $entities, string $operation = 'update', array $context = []): void
    {
        foreach ($entities as $entity) {
            $this->dispatchUpdate($entity, $operation, $context);
        }

        $this->logger->info('Dispatched batch duration update messages', [
            'entity_count' => count($entities),
            'operation' => $operation
        ]);
    }
}
