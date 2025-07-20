<?php

namespace App\Form\DataTransformer;

use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Tags Data Transformer
 * 
 * Transforms between array of tags and comma-separated string
 * for use in forms where tags are entered as text.
 */
class TagsTransformer implements DataTransformerInterface
{
    /**
     * Transform array to string (for form display)
     * 
     * @param array|null $tags
     * @return string
     */
    public function transform($tags): string
    {
        if (null === $tags || !is_array($tags)) {
            return '';
        }

        // Remove empty tags and join with commas
        $cleanTags = array_filter($tags, fn($tag) => !empty(trim($tag)));
        
        return implode(', ', $cleanTags);
    }

    /**
     * Transform string to array (for entity storage)
     * 
     * @param string $tagsString
     * @return array|null
     */
    public function reverseTransform($tagsString): ?array
    {
        if (empty($tagsString) || !is_string($tagsString)) {
            return null;
        }

        // Split by comma and clean up tags
        $tags = array_map('trim', explode(',', $tagsString));
        
        // Remove empty tags and duplicates
        $tags = array_filter($tags, fn($tag) => !empty($tag));
        $tags = array_unique($tags);
        
        // Re-index array to ensure sequential keys
        return array_values($tags);
    }
}
