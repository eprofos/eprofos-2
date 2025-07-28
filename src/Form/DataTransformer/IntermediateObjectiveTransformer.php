<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use App\DTO\Alternance\IntermediateObjectiveDTO;
use Symfony\Component\Form\DataTransformerInterface;

/**
 * Data transformer for intermediate objectives.
 *
 * Converts between array (stored in database) and DTO collection (used in forms)
 */
class IntermediateObjectiveTransformer implements DataTransformerInterface
{
    /**
     * Transform array data to DTO collection.
     *
     * @param mixed $value The array from database
     *
     * @return array Array of IntermediateObjectiveDTO
     */
    public function transform($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $dtos = [];
        foreach ($value as $objectiveData) {
            if (is_array($objectiveData)) {
                $dtos[] = IntermediateObjectiveDTO::fromArray($objectiveData);
            } elseif (is_string($objectiveData)) {
                // Handle legacy simple string objectives
                $dtos[] = new IntermediateObjectiveDTO(
                    title: $objectiveData,
                    description: '',
                    completed: false,
                );
            }
        }

        return $dtos;
    }

    /**
     * Transform DTO collection back to array for database storage.
     *
     * @param mixed $value Array of IntermediateObjectiveDTO
     *
     * @return array Array suitable for JSON storage
     */
    public function reverseTransform($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $arrayData = [];
        foreach ($value as $dto) {
            if ($dto instanceof IntermediateObjectiveDTO) {
                // Only include objectives with non-empty titles
                if (!empty(trim($dto->title))) {
                    $arrayData[] = $dto->toArray();
                }
            } elseif (is_array($dto)) {
                // Handle direct array input (shouldn't happen but safety check)
                if (!empty($dto['title'] ?? '')) {
                    $arrayData[] = $dto;
                }
            }
        }

        return $arrayData;
    }
}
