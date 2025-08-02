<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\Security\ContentAccessService;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Throwable;
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
        private readonly ContentAccessService $contentAccessService,
        private readonly LoggerInterface $logger,
    ) {}

    public function onKernelException(ExceptionEvent $event): void
    {
        try {
            $exception = $event->getThrowable();
            $request = $event->getRequest();

            $this->logger->info('AccessDeniedListener: Processing exception', [
                'exception_class' => get_class($exception),
                'exception_message' => $exception->getMessage(),
                'request_uri' => $request->getUri(),
                'request_method' => $request->getMethod(),
                'route' => $request->attributes->get('_route'),
                'route_params' => $request->attributes->get('_route_params', []),
                'user_ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent'),
            ]);

            // Only handle access denied exceptions for student content routes
            if (!$this->shouldHandle($exception, $request)) {
                $this->logger->debug('AccessDeniedListener: Exception not handled by this listener', [
                    'exception_class' => get_class($exception),
                    'route' => $request->attributes->get('_route'),
                    'reason' => 'Exception type or route pattern does not match criteria',
                ]);

                return;
            }

            $this->logger->info('AccessDeniedListener: Handling access denied exception for student content', [
                'route' => $request->attributes->get('_route'),
                'route_params' => $request->attributes->get('_route_params', []),
                'exception_message' => $exception->getMessage(),
            ]);

            // Create a user-friendly access denied response
            $response = $this->createAccessDeniedResponse($request, $exception);
            $event->setResponse($response);

            $this->logger->info('AccessDeniedListener: Successfully created access denied response', [
                'response_status' => $response->getStatusCode(),
                'route' => $request->attributes->get('_route'),
            ]);
        } catch (Throwable $handlingException) {
            $this->logger->error('AccessDeniedListener: Error while handling access denied exception', [
                'original_exception' => [
                    'class' => get_class($event->getThrowable()),
                    'message' => $event->getThrowable()->getMessage(),
                    'file' => $event->getThrowable()->getFile(),
                    'line' => $event->getThrowable()->getLine(),
                ],
                'handling_exception' => [
                    'class' => get_class($handlingException),
                    'message' => $handlingException->getMessage(),
                    'file' => $handlingException->getFile(),
                    'line' => $handlingException->getLine(),
                    'trace' => $handlingException->getTraceAsString(),
                ],
                'request_uri' => $event->getRequest()->getUri(),
                'route' => $event->getRequest()->attributes->get('_route'),
            ]);

            // Don't interfere with the original exception if we can't handle it properly
            // The original exception will continue to bubble up
        }
    }

    private function shouldHandle(Throwable $exception, Request $request): bool
    {
        try {
            $this->logger->debug('AccessDeniedListener: Checking if exception should be handled', [
                'exception_class' => get_class($exception),
                'request_route' => $request->attributes->get('_route', ''),
                'request_uri' => $request->getUri(),
            ]);

            // Check if it's an access denied exception
            $isAccessDeniedException = $exception instanceof AccessDeniedException || $exception instanceof AccessDeniedHttpException;

            if (!$isAccessDeniedException) {
                $this->logger->debug('AccessDeniedListener: Exception is not an access denied type', [
                    'exception_class' => get_class($exception),
                    'expected_types' => [AccessDeniedException::class, AccessDeniedHttpException::class],
                ]);

                return false;
            }

            // Only handle for student content routes
            $route = $request->attributes->get('_route', '');
            $isStudentRoute = str_starts_with($route, 'student_');
            $isContentRoute = str_contains($route, '_formation_')
                             || str_contains($route, '_module_')
                             || str_contains($route, '_chapter_')
                             || str_contains($route, '_course_')
                             || str_contains($route, '_exercise_')
                             || str_contains($route, '_qcm_');

            $shouldHandle = $isStudentRoute && $isContentRoute;

            $this->logger->debug('AccessDeniedListener: Route analysis completed', [
                'route' => $route,
                'is_student_route' => $isStudentRoute,
                'is_content_route' => $isContentRoute,
                'should_handle' => $shouldHandle,
                'route_patterns_checked' => [
                    '_formation_' => str_contains($route, '_formation_'),
                    '_module_' => str_contains($route, '_module_'),
                    '_chapter_' => str_contains($route, '_chapter_'),
                    '_course_' => str_contains($route, '_course_'),
                    '_exercise_' => str_contains($route, '_exercise_'),
                    '_qcm_' => str_contains($route, '_qcm_'),
                ],
            ]);

            return $shouldHandle;
        } catch (Throwable $checkException) {
            $this->logger->error('AccessDeniedListener: Error while checking if exception should be handled', [
                'check_exception' => [
                    'class' => get_class($checkException),
                    'message' => $checkException->getMessage(),
                    'file' => $checkException->getFile(),
                    'line' => $checkException->getLine(),
                ],
                'original_exception_class' => get_class($exception),
                'request_route' => $request->attributes->get('_route', 'unknown'),
            ]);

            // In case of error during check, don't handle the exception
            return false;
        }
    }

    private function createAccessDeniedResponse(Request $request, Throwable $exception): Response
    {
        try {
            $this->logger->info('AccessDeniedListener: Creating access denied response', [
                'request_route' => $request->attributes->get('_route'),
                'request_uri' => $request->getUri(),
                'exception_message' => $exception->getMessage(),
            ]);

            // Extract content information from the request if available
            $contentData = $this->extractContentData($request);

            $this->logger->debug('AccessDeniedListener: Content data extracted', [
                'content_data' => [
                    'has_content' => isset($contentData['content']),
                    'icon' => $contentData['icon'] ?? 'unknown',
                    'has_formation' => isset($contentData['formation']),
                    'available_sessions_count' => count($contentData['available_sessions'] ?? []),
                ],
            ]);

            // Render the custom access denied template
            $templateData = [
                'content' => $contentData['content'] ?? null,
                'content_icon' => $contentData['icon'] ?? 'book',
                'formation' => $contentData['formation'] ?? null,
                'available_sessions' => $contentData['available_sessions'] ?? [],
                'error_message' => $exception->getMessage(),
            ];

            $this->logger->debug('AccessDeniedListener: Rendering access denied template', [
                'template' => 'student/content/access_denied.html.twig',
                'template_data_keys' => array_keys($templateData),
            ]);

            $html = $this->twig->render('student/content/access_denied.html.twig', $templateData);

            $response = new Response($html, Response::HTTP_FORBIDDEN);

            $this->logger->info('AccessDeniedListener: Access denied response created successfully', [
                'response_status' => $response->getStatusCode(),
                'content_length' => strlen($html),
                'template_rendered' => 'student/content/access_denied.html.twig',
            ]);

            return $response;
        } catch (Throwable $responseException) {
            $this->logger->error('AccessDeniedListener: Error while creating access denied response', [
                'response_exception' => [
                    'class' => get_class($responseException),
                    'message' => $responseException->getMessage(),
                    'file' => $responseException->getFile(),
                    'line' => $responseException->getLine(),
                    'trace' => $responseException->getTraceAsString(),
                ],
                'original_exception' => [
                    'class' => get_class($exception),
                    'message' => $exception->getMessage(),
                ],
                'request_route' => $request->attributes->get('_route'),
                'request_uri' => $request->getUri(),
            ]);

            // Create a fallback response in case template rendering fails
            try {
                $fallbackHtml = sprintf(
                    '<html><head><title>Access Denied</title></head><body><h1>Access Denied</h1><p>%s</p></body></html>',
                    htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8'),
                );

                $fallbackResponse = new Response($fallbackHtml, Response::HTTP_FORBIDDEN);

                $this->logger->info('AccessDeniedListener: Created fallback access denied response', [
                    'reason' => 'Template rendering failed',
                    'fallback_response_status' => $fallbackResponse->getStatusCode(),
                ]);

                return $fallbackResponse;
            } catch (Throwable $fallbackException) {
                $this->logger->critical('AccessDeniedListener: Failed to create even fallback response', [
                    'fallback_exception' => [
                        'class' => get_class($fallbackException),
                        'message' => $fallbackException->getMessage(),
                    ],
                ]);

                // If even the fallback fails, rethrow the original response exception
                throw $responseException;
            }
        }
    }

    private function extractContentData(Request $request): array
    {
        try {
            $data = [];
            $route = $request->attributes->get('_route', '');
            $routeParams = $request->attributes->get('_route_params', []);

            $this->logger->debug('AccessDeniedListener: Extracting content data', [
                'route' => $route,
                'route_params' => $routeParams,
            ]);

            // Try to extract content entity from route parameters
            if (str_contains($route, '_formation_')) {
                $data['icon'] = 'graduation-cap';
                $this->logger->debug('AccessDeniedListener: Detected formation route', [
                    'icon' => $data['icon'],
                    'route' => $route,
                ]);
            // Formation entity would be injected by param converter
            } elseif (str_contains($route, '_module_')) {
                $data['icon'] = 'folder-open';
                $this->logger->debug('AccessDeniedListener: Detected module route', [
                    'icon' => $data['icon'],
                    'route' => $route,
                ]);
            } elseif (str_contains($route, '_chapter_')) {
                $data['icon'] = 'book-open';
                $this->logger->debug('AccessDeniedListener: Detected chapter route', [
                    'icon' => $data['icon'],
                    'route' => $route,
                ]);
            } elseif (str_contains($route, '_course_')) {
                $data['icon'] = 'play-circle';
                $this->logger->debug('AccessDeniedListener: Detected course route', [
                    'icon' => $data['icon'],
                    'route' => $route,
                ]);
            } elseif (str_contains($route, '_exercise_')) {
                $data['icon'] = 'edit';
                $this->logger->debug('AccessDeniedListener: Detected exercise route', [
                    'icon' => $data['icon'],
                    'route' => $route,
                ]);
            } elseif (str_contains($route, '_qcm_')) {
                $data['icon'] = 'question-circle';
                $this->logger->debug('AccessDeniedListener: Detected QCM route', [
                    'icon' => $data['icon'],
                    'route' => $route,
                ]);
            } else {
                $data['icon'] = 'book'; // Default icon
                $this->logger->debug('AccessDeniedListener: Using default icon for unknown content type', [
                    'icon' => $data['icon'],
                    'route' => $route,
                ]);
            }

            // Initialize empty arrays for additional data
            $data['available_sessions'] = [];

            // TODO: Extract actual content entities from route parameters
            // This would require checking for specific parameter names like 'formation', 'module', etc.
            // and potentially querying the database to get related data

            $this->logger->debug('AccessDeniedListener: Content data extraction completed', [
                'extracted_data' => $data,
                'route' => $route,
            ]);

            return $data;
        } catch (Throwable $extractException) {
            $this->logger->error('AccessDeniedListener: Error while extracting content data', [
                'extract_exception' => [
                    'class' => get_class($extractException),
                    'message' => $extractException->getMessage(),
                    'file' => $extractException->getFile(),
                    'line' => $extractException->getLine(),
                ],
                'route' => $request->attributes->get('_route', 'unknown'),
                'route_params' => $request->attributes->get('_route_params', []),
            ]);

            // Return minimal data in case of error
            return [
                'icon' => 'book',
                'available_sessions' => [],
            ];
        }
    }
}
