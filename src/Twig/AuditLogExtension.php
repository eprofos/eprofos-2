<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\Core\AuditLogService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twig Extension for Audit Log functionality.
 *
 * Provides Twig functions for accessing audit log services in templates.
 * Includes comprehensive logging for debugging and error tracking.
 */
class AuditLogExtension extends AbstractExtension
{
    public function __construct(
        private AuditLogService $auditLogService,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
    ) {
        $this->logger->info('AuditLogExtension initialized', [
            'extension_class' => self::class,
            'audit_service_class' => get_class($auditLogService),
            'url_generator_class' => get_class($urlGenerator),
        ]);
    }

    public function getFunctions(): array
    {
        try {
            $this->logger->debug('Registering Twig functions for AuditLogExtension');
            
            $functions = [
                new TwigFunction('audit_log_service', [$this, 'getAuditLogService']),
                new TwigFunction('is_entity_loggable', [$this, 'isEntityLoggable']),
                new TwigFunction('audit_history_url', [$this, 'getAuditHistoryUrl']),
            ];

            $this->logger->info('Twig functions registered successfully', [
                'function_count' => count($functions),
                'function_names' => ['audit_log_service', 'is_entity_loggable', 'audit_history_url'],
            ]);

            return $functions;

        } catch (\Exception $e) {
            $this->logger->error('Error while registering Twig functions', [
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);
            
            // Return empty array as fallback to prevent Twig from breaking
            return [];
        }
    }

    public function getFilters(): array
    {
        try {
            $this->logger->debug('Registering Twig filters for AuditLogExtension');
            
            $filters = [
                new TwigFilter('base64_encode', [$this, 'base64Encode']),
                new TwigFilter('base64_decode', [$this, 'base64Decode']),
            ];

            $this->logger->info('Twig filters registered successfully', [
                'filter_count' => count($filters),
                'filter_names' => ['base64_encode', 'base64_decode'],
            ]);

            return $filters;

        } catch (\Exception $e) {
            $this->logger->error('Error while registering Twig filters', [
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);
            
            // Return empty array as fallback to prevent Twig from breaking
            return [];
        }
    }

    /**
     * Get the audit log service instance.
     * 
     * @return AuditLogService
     */
    public function getAuditLogService(): AuditLogService
    {
        try {
            $this->logger->debug('Retrieving audit log service instance', [
                'service_class' => get_class($this->auditLogService),
                'service_hash' => spl_object_hash($this->auditLogService),
            ]);

            $this->logger->info('Audit log service instance retrieved successfully', [
                'service_class' => get_class($this->auditLogService),
            ]);

            return $this->auditLogService;

        } catch (\Exception $e) {
            $this->logger->error('Error while retrieving audit log service instance', [
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);
            
            // Re-throw the exception as this is critical functionality
            throw new \RuntimeException('Failed to retrieve audit log service: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if an entity is loggable.
     * 
     * @param object $entity The entity to check
     * @return bool True if the entity is loggable
     */
    public function isEntityLoggable(object $entity): bool
    {
        try {
            $entityClass = get_class($entity);
            $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;

            $this->logger->debug('Checking if entity is loggable via Twig extension', [
                'entity_class' => $entityClass,
                'entity_id' => $entityId,
                'entity_hash' => spl_object_hash($entity),
                'has_get_id_method' => method_exists($entity, 'getId'),
            ]);

            $isLoggable = $this->auditLogService->isEntityLoggable($entityClass);

            $this->logger->info('Entity loggable check completed via Twig extension', [
                'entity_class' => $entityClass,
                'entity_id' => $entityId,
                'is_loggable' => $isLoggable,
            ]);

            return $isLoggable;

        } catch (\Exception $e) {
            $this->logger->error('Error while checking if entity is loggable via Twig extension', [
                'entity_class' => get_class($entity),
                'entity_id' => method_exists($entity, 'getId') ? $entity->getId() : null,
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);
            
            // Return false as fallback - safer than throwing exception in Twig
            return false;
        }
    }

    /**
     * Generate audit history URL for an entity.
     * 
     * @param object $entity The entity to generate URL for
     * @return string The generated URL or empty string if failed
     */
    public function getAuditHistoryUrl(object $entity): string
    {
        try {
            $entityClass = get_class($entity);
            $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;

            $this->logger->debug('Generating audit history URL', [
                'entity_class' => $entityClass,
                'entity_id' => $entityId,
                'entity_hash' => spl_object_hash($entity),
                'has_get_id_method' => method_exists($entity, 'getId'),
            ]);

            if (!$entityId) {
                $this->logger->warning('Cannot generate audit history URL: entity has no ID', [
                    'entity_class' => $entityClass,
                    'entity_id' => $entityId,
                ]);
                return '';
            }

            // Check if entity is loggable before generating URL
            if (!$this->auditLogService->isEntityLoggable($entityClass)) {
                $this->logger->warning('Cannot generate audit history URL: entity is not loggable', [
                    'entity_class' => $entityClass,
                    'entity_id' => $entityId,
                ]);
                return '';
            }

            $this->logger->debug('Encoding entity class for URL generation', [
                'entity_class' => $entityClass,
                'entity_id' => $entityId,
            ]);

            $encodedClass = base64_encode($entityClass);

            $this->logger->debug('Entity class encoded successfully', [
                'entity_class' => $entityClass,
                'encoded_class' => $encodedClass,
                'encoded_length' => strlen($encodedClass),
            ]);

            $url = $this->urlGenerator->generate('admin_audit_entity_history', [
                'entityClass' => $encodedClass,
                'entityId' => $entityId,
            ]);

            $this->logger->info('Audit history URL generated successfully', [
                'entity_class' => $entityClass,
                'entity_id' => $entityId,
                'encoded_class' => $encodedClass,
                'generated_url' => $url,
                'url_length' => strlen($url),
            ]);

            return $url;

        } catch (\Exception $e) {
            $this->logger->error('Error while generating audit history URL', [
                'entity_class' => get_class($entity),
                'entity_id' => method_exists($entity, 'getId') ? $entity->getId() : null,
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);
            
            // Return empty string as fallback - safer than throwing exception in Twig
            return '';
        }
    }

    /**
     * Base64 encode filter.
     * 
     * @param string $data The data to encode
     * @return string The base64 encoded string
     */
    public function base64Encode(string $data): string
    {
        try {
            $this->logger->debug('Encoding data with base64', [
                'data_length' => strlen($data),
                'data_preview' => substr($data, 0, 50) . (strlen($data) > 50 ? '...' : ''),
                'is_empty' => empty($data),
            ]);

            if (empty($data)) {
                $this->logger->warning('Attempting to encode empty data with base64');
                return '';
            }

            $encoded = base64_encode($data);

            $this->logger->info('Data encoded successfully with base64', [
                'original_length' => strlen($data),
                'encoded_length' => strlen($encoded),
                'encoding_efficiency' => round((strlen($encoded) / strlen($data)) * 100, 2) . '%',
            ]);

            return $encoded;

        } catch (\Exception $e) {
            $this->logger->error('Error while encoding data with base64', [
                'data_length' => strlen($data),
                'data_preview' => substr($data, 0, 50) . (strlen($data) > 50 ? '...' : ''),
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);
            
            // Return empty string as fallback
            return '';
        }
    }

    /**
     * Base64 decode filter.
     * 
     * @param string $data The base64 encoded data to decode
     * @return string The decoded string
     */
    public function base64Decode(string $data): string
    {
        try {
            $this->logger->debug('Decoding data with base64', [
                'data_length' => strlen($data),
                'data_preview' => substr($data, 0, 50) . (strlen($data) > 50 ? '...' : ''),
                'is_empty' => empty($data),
                'is_valid_base64' => base64_encode(base64_decode($data, true)) === $data,
            ]);

            if (empty($data)) {
                $this->logger->warning('Attempting to decode empty data with base64');
                return '';
            }

            // Validate base64 format before decoding
            if (base64_encode(base64_decode($data, true)) !== $data) {
                $this->logger->error('Invalid base64 data provided for decoding', [
                    'data_length' => strlen($data),
                    'data_preview' => substr($data, 0, 50) . (strlen($data) > 50 ? '...' : ''),
                ]);
                return '';
            }

            $decoded = base64_decode($data, true);

            if ($decoded === false) {
                $this->logger->error('Base64 decoding failed', [
                    'data_length' => strlen($data),
                    'data_preview' => substr($data, 0, 50) . (strlen($data) > 50 ? '...' : ''),
                ]);
                return '';
            }

            $this->logger->info('Data decoded successfully with base64', [
                'encoded_length' => strlen($data),
                'decoded_length' => strlen($decoded),
                'decoding_efficiency' => round((strlen($decoded) / strlen($data)) * 100, 2) . '%',
                'decoded_preview' => substr($decoded, 0, 50) . (strlen($decoded) > 50 ? '...' : ''),
            ]);

            return $decoded;

        } catch (\Exception $e) {
            $this->logger->error('Error while decoding data with base64', [
                'data_length' => strlen($data),
                'data_preview' => substr($data, 0, 50) . (strlen($data) > 50 ? '...' : ''),
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);
            
            // Return empty string as fallback
            return '';
        }
    }
}
