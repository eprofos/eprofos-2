<?php

declare(strict_types=1);

namespace App\Controller\Admin\Core;

use App\Service\Core\AuditLogService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin Audit Log Controller.
 *
 * Handles viewing change history for loggable entities.
 * Provides comprehensive audit trail functionality for EPROFOS entities.
 */
#[Route('/admin/audit', name: 'admin_audit_')]
#[IsGranted('ROLE_ADMIN')]
class AuditLogController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private AuditLogService $auditLogService,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Display change history for a specific entity.
     */
    #[Route('/entity/{entityClass}/{entityId}', name: 'entity_history', methods: ['GET'])]
    public function entityHistory(
        Request $request,
        string $entityClass,
        int $entityId,
    ): Response {
        // Decode the entity class name
        $entityClass = base64_decode($entityClass, true);

        // Verify the entity class is loggable
        if (!$this->auditLogService->isEntityLoggable($entityClass)) {
            $this->addFlash('error', 'Cette entité n\'est pas auditable.');

            return $this->redirectToRoute('admin_dashboard');
        }

        // Find the entity
        $entity = $this->entityManager->getRepository($entityClass)->find($entityId);
        if (!$entity) {
            $this->addFlash('error', 'Entité non trouvée.');

            return $this->redirectToRoute('admin_dashboard');
        }

        $this->logger->info('Admin audit log accessed', [
            'user' => $this->getUser()?->getUserIdentifier(),
            'entity_class' => $entityClass,
            'entity_id' => $entityId,
        ]);

        // Get pagination parameters
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;

        // Get change history
        $changes = $this->auditLogService->getFormattedEntityChanges($entity, $limit * $page);

        // Paginate results
        $totalChanges = count($changes);
        $changes = array_slice($changes, ($page - 1) * $limit, $limit);
        $totalPages = max(1, ceil($totalChanges / $limit));

        // Process changes to add comparison data
        $processedChanges = [];
        for ($i = 0; $i < count($changes); $i++) {
            $change = $changes[$i];
            $previousData = [];

            // Get previous version data for comparison
            if ($i < count($changes) - 1) {
                $previousData = $changes[$i + 1]['data'] ?? [];
            }

            $fieldChanges = $this->auditLogService->compareVersions($previousData, $change['data']);

            // Process field changes to add human-readable names and formatted values
            $processedFieldChanges = [];
            foreach ($fieldChanges as $fieldName => $fieldChange) {
                $processedFieldChanges[$fieldName] = [
                    'old' => $fieldChange['old'],
                    'new' => $fieldChange['new'],
                    'type' => $fieldChange['type'],
                    'humanName' => $this->auditLogService->getHumanReadableFieldName($fieldName, $entityClass),
                    'formattedOld' => $this->auditLogService->formatFieldValue($fieldChange['old'], $fieldName),
                    'formattedNew' => $this->auditLogService->formatFieldValue($fieldChange['new'], $fieldName),
                ];
            }

            $change['fieldChanges'] = $processedFieldChanges;
            $processedChanges[] = $change;
        }

        // Get entity display name
        $entityDisplayName = $this->getEntityDisplayName($entity);

        return $this->render('admin/audit/entity_history.html.twig', [
            'entity' => $entity,
            'entityClass' => $entityClass,
            'entityDisplayName' => $entityDisplayName,
            'changes' => $processedChanges,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalChanges' => $totalChanges,
        ]);
    }

    /**
     * Display all loggable entities overview.
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $this->logger->info('Admin audit overview accessed', [
            'user' => $this->getUser()?->getUserIdentifier(),
        ]);

        $loggableEntities = $this->auditLogService->getLoggableEntities();

        // Get entity counts and recent activity
        $entityStats = [];
        foreach ($loggableEntities as $entityClass) {
            $repository = $this->entityManager->getRepository($entityClass);
            $shortName = (new ReflectionClass($entityClass))->getShortName();

            $entityStats[] = [
                'class' => $entityClass,
                'shortName' => $shortName,
                'displayName' => $this->getEntityClassDisplayName($entityClass),
                'count' => $repository->count([]),
                'encodedClass' => base64_encode($entityClass),
            ];
        }

        return $this->render('admin/audit/index.html.twig', [
            'entityStats' => $entityStats,
        ]);
    }

    /**
     * Get a human-readable display name for an entity.
     */
    private function getEntityDisplayName(object $entity): string
    {
        if (method_exists($entity, '__toString')) {
            return (string) $entity;
        }

        if (method_exists($entity, 'getTitle')) {
            return $entity->getTitle();
        }

        if (method_exists($entity, 'getName')) {
            return $entity->getName();
        }

        if (method_exists($entity, 'getId')) {
            return (new ReflectionClass($entity))->getShortName() . ' #' . $entity->getId();
        }

        return (new ReflectionClass($entity))->getShortName();
    }

    /**
     * Get a human-readable display name for an entity class.
     */
    private function getEntityClassDisplayName(string $entityClass): string
    {
        $shortName = (new ReflectionClass($entityClass))->getShortName();

        $displayNames = [
            'Formation' => 'Formations',
            'Category' => 'Catégories',
            'Module' => 'Modules',
            'Chapter' => 'Chapitres',
            'Course' => 'Cours',
            'Exercise' => 'Exercices',
            'QCM' => 'QCM',
            'Session' => 'Sessions',
            'SessionRegistration' => 'Inscriptions aux sessions',
        ];

        return $displayNames[$shortName] ?? $shortName;
    }
}
