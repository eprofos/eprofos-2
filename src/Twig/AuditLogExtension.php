<?php

namespace App\Twig;

use App\Service\AuditLogService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twig Extension for Audit Log functionality
 * 
 * Provides Twig functions for accessing audit log services in templates.
 */
class AuditLogExtension extends AbstractExtension
{
    public function __construct(
        private AuditLogService $auditLogService,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('audit_log_service', [$this, 'getAuditLogService']),
            new TwigFunction('is_entity_loggable', [$this, 'isEntityLoggable']),
            new TwigFunction('audit_history_url', [$this, 'getAuditHistoryUrl']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('base64_encode', [$this, 'base64Encode']),
            new TwigFilter('base64_decode', [$this, 'base64Decode']),
        ];
    }

    /**
     * Get the audit log service instance
     */
    public function getAuditLogService(): AuditLogService
    {
        return $this->auditLogService;
    }

    /**
     * Check if an entity is loggable
     */
    public function isEntityLoggable(object $entity): bool
    {
        return $this->auditLogService->isEntityLoggable(get_class($entity));
    }

    /**
     * Generate audit history URL for an entity
     */
    public function getAuditHistoryUrl(object $entity): string
    {
        $entityClass = get_class($entity);
        $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;
        
        if (!$entityId) {
            return '';
        }

        $encodedClass = base64_encode($entityClass);
        
        return $this->urlGenerator->generate('admin_audit_entity_history', [
            'entityClass' => $encodedClass,
            'entityId' => $entityId
        ]);
    }

    /**
     * Base64 encode filter
     */
    public function base64Encode(string $data): string
    {
        return base64_encode($data);
    }

    /**
     * Base64 decode filter
     */
    public function base64Decode(string $data): string
    {
        return base64_decode($data);
    }
}
