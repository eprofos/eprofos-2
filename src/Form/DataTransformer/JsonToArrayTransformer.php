<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * JSON to Array Data Transformer.
 *
 * Transforms between JSON strings and PHP arrays for form fields.
 */
class JsonToArrayTransformer implements DataTransformerInterface
{
    /**
     * Transform array to JSON string for display in form.
     */
    public function transform(mixed $value): string
    {
        if (null === $value || [] === $value) {
            return '';
        }

        if (!is_array($value)) {
            return '';
        }

        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        } catch (\JsonException $e) {
            throw new TransformationFailedException('Could not encode array to JSON: ' . $e->getMessage());
        }
    }

    /**
     * Transform JSON string to array for entity.
     */
    public function reverseTransform(mixed $value): ?array
    {
        if (empty($value) || !is_string($value)) {
            return null;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (\JsonException $e) {
            throw new TransformationFailedException('Could not decode JSON string: ' . $e->getMessage());
        }
    }
}
