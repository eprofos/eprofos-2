<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\Security\ContentAccessService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

/**
 * AccessDeniedListener handles access denied exceptions for content access.
 *
 * This listener provides user-friendly access denied pages for training content
 * with helpful information about how to gain access.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 0)]
class AccessDeniedListener
{
    public function __construct(
        private readonly Environment $twig,
        private readonly ContentAccessService $contentAccessService
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Only handle access denied exceptions for student content routes
        if (!$this->shouldHandle($exception, $request)) {
            return;
        }

        // Create a user-friendly access denied response
        $response = $this->createAccessDeniedResponse($request, $exception);
        $event->setResponse($response);
    }

    private function shouldHandle(\Throwable $exception, Request $request): bool
    {
        // Check if it's an access denied exception
        if (!$exception instanceof AccessDeniedException && !$exception instanceof AccessDeniedHttpException) {
            return false;
        }

        // Only handle for student content routes
        $route = $request->attributes->get('_route', '');
        return str_starts_with($route, 'student_') && 
               (str_contains($route, '_formation_') || 
                str_contains($route, '_module_') || 
                str_contains($route, '_chapter_') || 
                str_contains($route, '_course_') || 
                str_contains($route, '_exercise_') || 
                str_contains($route, '_qcm_'));
    }

    private function createAccessDeniedResponse(Request $request, \Throwable $exception): Response
    {
        // Extract content information from the request if available
        $contentData = $this->extractContentData($request);

        // Render the custom access denied template
        $html = $this->twig->render('student/content/access_denied.html.twig', [
            'content' => $contentData['content'] ?? null,
            'content_icon' => $contentData['icon'] ?? 'book',
            'formation' => $contentData['formation'] ?? null,
            'available_sessions' => $contentData['available_sessions'] ?? [],
            'error_message' => $exception->getMessage(),
        ]);

        return new Response($html, Response::HTTP_FORBIDDEN);
    }

    private function extractContentData(Request $request): array
    {
        $data = [];
        $route = $request->attributes->get('_route', '');

        // Try to extract content entity from route parameters
        if (str_contains($route, '_formation_')) {
            $data['icon'] = 'graduation-cap';
            // Formation entity would be injected by param converter
        } elseif (str_contains($route, '_module_')) {
            $data['icon'] = 'folder-open';
        } elseif (str_contains($route, '_chapter_')) {
            $data['icon'] = 'book-open';
        } elseif (str_contains($route, '_course_')) {
            $data['icon'] = 'play-circle';
        } elseif (str_contains($route, '_exercise_')) {
            $data['icon'] = 'edit';
        } elseif (str_contains($route, '_qcm_')) {
            $data['icon'] = 'question-circle';
        }

        return $data;
    }
}
