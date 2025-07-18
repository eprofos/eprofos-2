<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigTest;

/**
 * Twig extension for file and PDF related functions
 */
class FileExtension extends AbstractExtension
{
    public function getTests(): array
    {
        return [
            new TwigTest('file_exists', [$this, 'fileExists']),
        ];
    }

    public function fileExists(string $filename): bool
    {
        return file_exists($filename);
    }
}
