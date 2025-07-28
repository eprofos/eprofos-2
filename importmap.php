<?php

/**
 * Returns the importmap for this application.
 *
 * - "path" is a path inside the asset mapper system. Use the
 *     "debug:asset-map" command to see the full list of paths.
 *
 * - "entrypoint" (JavaScript only) set to true for any module that will
 *     be used as an "entrypoint" (and passed to the importmap() Twig function).
 *
 * The "importmap:require" command can be used to add new entries to this file.
 */
return [
    'public' => [
        'path' => './assets/public.js',
        'entrypoint' => true,
    ],
    'private' => [
        'path' => './assets/private.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => [
        'version' => '3.2.2',
    ],
    '@symfony/stimulus-bundle' => [
        'path' => './vendor/symfony/stimulus-bundle/assets/dist/loader.js',
    ],
    '@hotwired/turbo' => [
        'version' => '7.3.0',
    ],
    'bootstrap' => [
        'version' => '5.3.7',
    ],
    '@popperjs/core' => [
        'version' => '2.11.8',
    ],
    'bootstrap/dist/css/bootstrap.min.css' => [
        'version' => '5.3.7',
        'type' => 'css',
    ],
    '@fortawesome/fontawesome-free' => [
        'version' => '6.7.2',
    ],
    '@fortawesome/fontawesome-free/css/fontawesome.min.css' => [
        'version' => '6.7.2',
        'type' => 'css',
    ],
    '@fortawesome/fontawesome-free/css/solid.min.css' => [
        'version' => '6.7.2',
        'type' => 'css',
    ],
    '@fortawesome/fontawesome-free/css/brands.min.css' => [
        'version' => '6.7.2',
        'type' => 'css',
    ],
    '@tabler/core' => [
        'version' => '1.3.2',
    ],
    '@tabler/core/dist/css/tabler.min.css' => [
        'version' => '1.3.2',
        'type' => 'css',
    ],
    'chart.js' => [
        'version' => '4.5.0',
    ],
    '@kurkle/color' => [
        'version' => '0.3.4',
    ],
    'chart.js/auto' => [
        'version' => '4.5.0',
    ],
];
