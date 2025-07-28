<?php

declare(strict_types=1);

namespace App\DTO\Alternance;

use DateTime;
use DateTimeInterface;
use Exception;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for intermediate objectives in mission assignments.
 *
 * Represents a structured objective with completion tracking
 */
class IntermediateObjectiveDTO
{
    #[Assert\NotBlank(message: 'Le titre de l\'objectif est obligatoire.')]
    #[Assert\Length(
        min: 5,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.',
    )]
    public string $title = '';

    #[Assert\Length(
        max: 1000,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.',
    )]
    public string $description = '';

    public bool $completed = false;

    public ?DateTimeInterface $completionDate = null;

    public function __construct(string $title = '', string $description = '', bool $completed = false, ?DateTimeInterface $completionDate = null)
    {
        $this->title = $title;
        $this->description = $description;
        $this->completed = $completed;
        $this->completionDate = $completionDate;
    }

    public function __toString(): string
    {
        return $this->title;
    }

    /**
     * Create DTO from array data.
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();

        // Handle different possible data structures
        if (isset($data['title'])) {
            // Normal associative array
            $dto->title = (string) $data['title'];

            // Handle description - be defensive about data types
            if (isset($data['description'])) {
                if (is_string($data['description'])) {
                    $dto->description = $data['description'];
                } elseif (is_bool($data['description'])) {
                    $dto->description = ''; // Convert boolean to empty string
                } elseif (is_numeric($data['description'])) {
                    $dto->description = ''; // Convert numeric to empty string
                } else {
                    $dto->description = '';
                }
            } else {
                $dto->description = '';
            }

            $dto->completed = isset($data['completed']) ? (bool) $data['completed'] : false;

            // Handle completion date
            if (isset($data['completion_date']) && $data['completion_date']) {
                if ($data['completion_date'] instanceof DateTimeInterface) {
                    $dto->completionDate = $data['completion_date'];
                } elseif (is_string($data['completion_date'])) {
                    try {
                        $dto->completionDate = new DateTime($data['completion_date']);
                    } catch (Exception $e) {
                        $dto->completionDate = null;
                    }
                }
            }
        } else {
            // Handle malformed data or set defaults
            $dto->title = 'Objectif sans titre';
            $dto->description = '';
            $dto->completed = false;
            $dto->completionDate = null;
        }

        return $dto;
    }

    /**
     * Convert DTO to array.
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'completed' => $this->completed,
            'completion_date' => $this->completionDate?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get completion status label.
     */
    public function getCompletionStatusLabel(): string
    {
        return $this->completed ? 'Terminé' : 'En cours';
    }

    /**
     * Get completion status CSS class.
     */
    public function getCompletionStatusClass(): string
    {
        return $this->completed ? 'badge-success' : 'badge-warning';
    }

    /**
     * Get completion date formatted.
     */
    public function getFormattedCompletionDate(): string
    {
        if (!$this->completionDate) {
            return '';
        }

        return $this->completionDate->format('d/m/Y');
    }

    /**
     * Mark objective as completed.
     */
    public function markCompleted(): void
    {
        $this->completed = true;
        $this->completionDate = new DateTime();
    }

    /**
     * Mark objective as not completed.
     */
    public function markIncomplete(): void
    {
        $this->completed = false;
        $this->completionDate = null;
    }

    /**
     * Check if objective has a meaningful description.
     */
    public function hasDescription(): bool
    {
        return !empty(trim($this->description)) && $this->description !== $this->title;
    }
}
