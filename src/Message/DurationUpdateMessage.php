<?php

namespace App\Message;

/**
 * Message for asynchronous duration updates
 */
class DurationUpdateMessage
{
    public function __construct(
        private string $entityClass,
        private int $entityId,
        private string $operation = 'update',
        private array $context = []
    ) {
    }

    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
