<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Transforms between JSON array and string for form fields.
 */
class JsonToStringTransformer implements DataTransformerInterface
{
    /**
     * Transforms an array to a JSON string for the form field.
     */
    public function transform(mixed $value): string
    {
        if ($value === null || $value === []) {
            return '';
        }

        if (!is_array($value)) {
            return '';
        }

        return json_encode($value, JSON_PRETTY_PRINT) ?: '';
    }

    /**
     * Transforms a JSON string back to an array.
     */
    public function reverseTransform(mixed $value): ?array
    {
        if (empty($value)) {
            return null;
        }

        if (!is_string($value)) {
            throw new TransformationFailedException('Expected a string.');
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new TransformationFailedException('Invalid JSON: ' . json_last_error_msg());
        }

        return $decoded;
    }
}
