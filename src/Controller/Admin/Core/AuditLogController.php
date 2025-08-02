<?php

declare(strict_types=1);

namespace App\Controller\Admin\Core;

use App\Service\Core\AuditLogService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

/**
 * Admin Audit Log Controller.
 *
 * Handles viewing change history for loggable entities.
 * Provides comprehensive audit trail functionality for EPROFOS entities.
 */
#[Route('/admin/audit')]
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
    #[Route('/entity/{entityClass}/{entityId}', name: 'admin_audit_entity_history', methods: ['GET'])]
    public function entityHistory(
        Request $request,
        string $entityClass,
        int $entityId,
    ): Response {
        $startTime = microtime(true);
        $sessionId = $request->getSession()->getId();
        $userId = $this->getUser()?->getUserIdentifier();
        $clientIp = $request->getClientIp();

        $this->logger->info('Audit entity history request initiated', [
            'session_id' => $sessionId,
            'user_id' => $userId,
            'client_ip' => $clientIp,
            'entity_class_encoded' => $entityClass,
            'entity_id' => $entityId,
            'request_uri' => $request->getRequestUri(),
            'user_agent' => $request->headers->get('User-Agent'),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            // Step 1: Decode and validate entity class
            $this->logger->debug('Decoding entity class parameter', [
                'encoded_class' => $entityClass,
                'session_id' => $sessionId,
            ]);

            $decodedEntityClass = base64_decode($entityClass, true);
            
            if ($decodedEntityClass === false) {
                $this->logger->error('Failed to decode entity class parameter', [
                    'encoded_class' => $entityClass,
                    'session_id' => $sessionId,
                    'user_id' => $userId,
                ]);
                throw new InvalidArgumentException('Invalid entity class parameter encoding');
            }

            $this->logger->debug('Entity class decoded successfully', [
                'decoded_class' => $decodedEntityClass,
                'session_id' => $sessionId,
            ]);

            // Step 2: Verify the entity class is loggable
            $this->logger->debug('Checking if entity class is loggable', [
                'entity_class' => $decodedEntityClass,
                'session_id' => $sessionId,
            ]);

            if (!$this->auditLogService->isEntityLoggable($decodedEntityClass)) {
                $this->logger->warning('Attempted to access audit history for non-loggable entity', [
                    'entity_class' => $decodedEntityClass,
                    'session_id' => $sessionId,
                    'user_id' => $userId,
                    'client_ip' => $clientIp,
                ]);

                $this->addFlash('error', 'Cette entité n\'est pas auditable.');
                return $this->redirectToRoute('admin_dashboard');
            }

            $this->logger->debug('Entity class verified as loggable', [
                'entity_class' => $decodedEntityClass,
                'session_id' => $sessionId,
            ]);

            // Step 3: Find the entity
            $this->logger->debug('Searching for entity in database', [
                'entity_class' => $decodedEntityClass,
                'entity_id' => $entityId,
                'session_id' => $sessionId,
            ]);

            $repository = $this->entityManager->getRepository($decodedEntityClass);
            $entity = $repository->find($entityId);
            
            if (!$entity) {
                $this->logger->warning('Entity not found in database', [
                    'entity_class' => $decodedEntityClass,
                    'entity_id' => $entityId,
                    'session_id' => $sessionId,
                    'user_id' => $userId,
                ]);

                $this->addFlash('error', 'Entité non trouvée.');
                return $this->redirectToRoute('admin_dashboard');
            }

            $this->logger->info('Entity found successfully', [
                'entity_class' => $decodedEntityClass,
                'entity_id' => $entityId,
                'entity_string' => method_exists($entity, '__toString') ? (string) $entity : 'N/A',
                'session_id' => $sessionId,
            ]);

            // Step 4: Get pagination parameters
            $page = max(1, $request->query->getInt('page', 1));
            $limit = 20;

            $this->logger->debug('Pagination parameters extracted', [
                'page' => $page,
                'limit' => $limit,
                'session_id' => $sessionId,
            ]);

            // Step 5: Get change history
            $this->logger->debug('Retrieving entity change history', [
                'entity_class' => $decodedEntityClass,
                'entity_id' => $entityId,
                'max_results' => $limit * $page,
                'session_id' => $sessionId,
            ]);

            $changes = $this->auditLogService->getFormattedEntityChanges($entity, $limit * $page);
            
            $this->logger->info('Entity change history retrieved', [
                'entity_class' => $decodedEntityClass,
                'entity_id' => $entityId,
                'total_changes_found' => count($changes),
                'session_id' => $sessionId,
            ]);

            // Step 6: Paginate results
            $totalChanges = count($changes);
            $changes = array_slice($changes, ($page - 1) * $limit, $limit);
            $totalPages = max(1, ceil($totalChanges / $limit));

            $this->logger->debug('Results paginated', [
                'total_changes' => $totalChanges,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'changes_on_page' => count($changes),
                'session_id' => $sessionId,
            ]);

            // Step 7: Process changes to add comparison data
            $this->logger->debug('Processing changes for comparison data', [
                'changes_to_process' => count($changes),
                'session_id' => $sessionId,
            ]);

            $processedChanges = [];
            for ($i = 0; $i < count($changes); $i++) {
                try {
                    $change = $changes[$i];
                    $previousData = [];

                    $this->logger->debug('Processing change entry', [
                        'change_index' => $i,
                        'change_id' => $change['id'] ?? 'unknown',
                        'change_action' => $change['action'] ?? 'unknown',
                        'change_date' => $change['loggedAt'] ?? 'unknown',
                        'session_id' => $sessionId,
                    ]);

                    // Get previous version data for comparison
                    if ($i < count($changes) - 1) {
                        $previousData = $changes[$i + 1]['data'] ?? [];
                    }

                    $fieldChanges = $this->auditLogService->compareVersions($previousData, $change['data']);

                    $this->logger->debug('Field changes computed', [
                        'change_index' => $i,
                        'fields_changed' => array_keys($fieldChanges),
                        'field_count' => count($fieldChanges),
                        'session_id' => $sessionId,
                    ]);

                    // Process field changes to add human-readable names and formatted values
                    $processedFieldChanges = [];
                    foreach ($fieldChanges as $fieldName => $fieldChange) {
                        try {
                            $humanName = $this->auditLogService->getHumanReadableFieldName($fieldName, $decodedEntityClass);
                            $formattedOld = $this->auditLogService->formatFieldValue($fieldChange['old'], $fieldName);
                            $formattedNew = $this->auditLogService->formatFieldValue($fieldChange['new'], $fieldName);

                            $processedFieldChanges[$fieldName] = [
                                'old' => $fieldChange['old'],
                                'new' => $fieldChange['new'],
                                'type' => $fieldChange['type'],
                                'humanName' => $humanName,
                                'formattedOld' => $formattedOld,
                                'formattedNew' => $formattedNew,
                            ];

                            $this->logger->debug('Field change processed', [
                                'field_name' => $fieldName,
                                'human_name' => $humanName,
                                'change_type' => $fieldChange['type'],
                                'session_id' => $sessionId,
                            ]);
                        } catch (Exception $e) {
                            $this->logger->error('Error processing field change', [
                                'field_name' => $fieldName,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                                'session_id' => $sessionId,
                            ]);
                            
                            // Keep processing other fields
                            $processedFieldChanges[$fieldName] = [
                                'old' => $fieldChange['old'] ?? null,
                                'new' => $fieldChange['new'] ?? null,
                                'type' => $fieldChange['type'] ?? 'unknown',
                                'humanName' => $fieldName,
                                'formattedOld' => 'Erreur de formatage',
                                'formattedNew' => 'Erreur de formatage',
                            ];
                        }
                    }

                    $change['fieldChanges'] = $processedFieldChanges;
                    $processedChanges[] = $change;

                } catch (Exception $e) {
                    $this->logger->error('Error processing individual change', [
                        'change_index' => $i,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'session_id' => $sessionId,
                    ]);
                    
                    // Continue processing other changes
                    continue;
                }
            }

            $this->logger->info('All changes processed successfully', [
                'total_processed' => count($processedChanges),
                'session_id' => $sessionId,
            ]);

            // Step 8: Get entity display name
            $this->logger->debug('Getting entity display name', [
                'entity_class' => $decodedEntityClass,
                'entity_id' => $entityId,
                'session_id' => $sessionId,
            ]);

            $entityDisplayName = $this->getEntityDisplayName($entity);

            $this->logger->debug('Entity display name retrieved', [
                'display_name' => $entityDisplayName,
                'session_id' => $sessionId,
            ]);

            // Step 9: Prepare response
            $executionTime = microtime(true) - $startTime;
            
            $this->logger->info('Audit entity history request completed successfully', [
                'entity_class' => $decodedEntityClass,
                'entity_id' => $entityId,
                'entity_display_name' => $entityDisplayName,
                'total_changes' => $totalChanges,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'execution_time_ms' => round($executionTime * 1000, 2),
                'session_id' => $sessionId,
                'user_id' => $userId,
            ]);

            return $this->render('admin/audit/entity_history.html.twig', [
                'entity' => $entity,
                'entityClass' => $decodedEntityClass,
                'entityDisplayName' => $entityDisplayName,
                'changes' => $processedChanges,
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'totalChanges' => $totalChanges,
            ]);

        } catch (InvalidArgumentException $e) {
            $this->logger->error('Invalid argument provided to audit entity history', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'entity_class_param' => $entityClass,
                'entity_id' => $entityId,
                'session_id' => $sessionId,
                'user_id' => $userId,
                'client_ip' => $clientIp,
            ]);

            $this->addFlash('error', 'Paramètres invalides fournis.');
            return $this->redirectToRoute('admin_dashboard');

        } catch (RuntimeException $e) {
            $this->logger->error('Runtime error in audit entity history', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'entity_class_param' => $entityClass,
                'entity_id' => $entityId,
                'session_id' => $sessionId,
                'user_id' => $userId,
                'client_ip' => $clientIp,
            ]);

            $this->addFlash('error', 'Une erreur s\'est produite lors de la récupération de l\'historique.');
            return $this->redirectToRoute('admin_dashboard');

        } catch (Throwable $e) {
            $this->logger->critical('Critical error in audit entity history', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'entity_class_param' => $entityClass,
                'entity_id' => $entityId,
                'session_id' => $sessionId,
                'user_id' => $userId,
                'client_ip' => $clientIp,
                'request_method' => $request->getMethod(),
                'request_uri' => $request->getRequestUri(),
            ]);

            $this->addFlash('error', 'Une erreur critique s\'est produite. L\'équipe technique a été notifiée.');
            return $this->redirectToRoute('admin_dashboard');
        }
    }

    /**
     * Display all loggable entities overview.
     */
    #[Route('/', name: 'admin_audit_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $startTime = microtime(true);
        $sessionId = $request->getSession()->getId();
        $userId = $this->getUser()?->getUserIdentifier();
        $clientIp = $request->getClientIp();

        $this->logger->info('Audit overview request initiated', [
            'session_id' => $sessionId,
            'user_id' => $userId,
            'client_ip' => $clientIp,
            'request_uri' => $request->getRequestUri(),
            'user_agent' => $request->headers->get('User-Agent'),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            // Step 1: Get loggable entities
            $this->logger->debug('Retrieving loggable entities list', [
                'session_id' => $sessionId,
            ]);

            $loggableEntities = $this->auditLogService->getLoggableEntities();

            $this->logger->info('Loggable entities retrieved', [
                'entity_count' => count($loggableEntities),
                'entities' => $loggableEntities,
                'session_id' => $sessionId,
            ]);

            // Step 2: Get entity counts and recent activity
            $this->logger->debug('Processing entity statistics', [
                'entities_to_process' => count($loggableEntities),
                'session_id' => $sessionId,
            ]);

            $entityStats = [];
            $totalEntitiesProcessed = 0;
            $totalRecordsCount = 0;

            foreach ($loggableEntities as $entityClass) {
                try {
                    $this->logger->debug('Processing entity statistics', [
                        'entity_class' => $entityClass,
                        'session_id' => $sessionId,
                    ]);

                    $repository = $this->entityManager->getRepository($entityClass);
                    
                    if (!$repository) {
                        $this->logger->warning('Repository not found for entity class', [
                            'entity_class' => $entityClass,
                            'session_id' => $sessionId,
                        ]);
                        continue;
                    }

                    $reflection = new ReflectionClass($entityClass);
                    $shortName = $reflection->getShortName();

                    $this->logger->debug('Getting entity count', [
                        'entity_class' => $entityClass,
                        'short_name' => $shortName,
                        'session_id' => $sessionId,
                    ]);

                    $entityCount = $repository->count([]);
                    $totalRecordsCount += $entityCount;

                    $displayName = $this->getEntityClassDisplayName($entityClass);
                    $encodedClass = base64_encode($entityClass);

                    $entityStats[] = [
                        'class' => $entityClass,
                        'shortName' => $shortName,
                        'displayName' => $displayName,
                        'count' => $entityCount,
                        'encodedClass' => $encodedClass,
                    ];

                    $this->logger->debug('Entity statistics processed', [
                        'entity_class' => $entityClass,
                        'short_name' => $shortName,
                        'display_name' => $displayName,
                        'count' => $entityCount,
                        'encoded_class' => $encodedClass,
                        'session_id' => $sessionId,
                    ]);

                    $totalEntitiesProcessed++;

                } catch (ReflectionException $e) {
                    $this->logger->error('Reflection error processing entity', [
                        'entity_class' => $entityClass,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'session_id' => $sessionId,
                    ]);
                    continue;

                } catch (Exception $e) {
                    $this->logger->error('Error processing entity statistics', [
                        'entity_class' => $entityClass,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'session_id' => $sessionId,
                    ]);
                    continue;
                }
            }

            $executionTime = microtime(true) - $startTime;

            $this->logger->info('Audit overview request completed successfully', [
                'total_loggable_entities' => count($loggableEntities),
                'entities_processed' => $totalEntitiesProcessed,
                'total_records_count' => $totalRecordsCount,
                'execution_time_ms' => round($executionTime * 1000, 2),
                'session_id' => $sessionId,
                'user_id' => $userId,
            ]);

            return $this->render('admin/audit/index.html.twig', [
                'entityStats' => $entityStats,
            ]);

        } catch (RuntimeException $e) {
            $this->logger->error('Runtime error in audit overview', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'session_id' => $sessionId,
                'user_id' => $userId,
                'client_ip' => $clientIp,
            ]);

            $this->addFlash('error', 'Une erreur s\'est produite lors de la récupération des données d\'audit.');
            return $this->redirectToRoute('admin_dashboard');

        } catch (Throwable $e) {
            $this->logger->critical('Critical error in audit overview', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'session_id' => $sessionId,
                'user_id' => $userId,
                'client_ip' => $clientIp,
                'request_method' => $request->getMethod(),
                'request_uri' => $request->getRequestUri(),
            ]);

            $this->addFlash('error', 'Une erreur critique s\'est produite. L\'équipe technique a été notifiée.');
            return $this->redirectToRoute('admin_dashboard');
        }
    }

    /**
     * Get a human-readable display name for an entity.
     */
    private function getEntityDisplayName(object $entity): string
    {
        try {
            $this->logger->debug('Getting entity display name', [
                'entity_class' => get_class($entity),
                'entity_id' => method_exists($entity, 'getId') ? $entity->getId() : 'N/A',
            ]);

            if (method_exists($entity, '__toString')) {
                $displayName = (string) $entity;
                $this->logger->debug('Entity display name from __toString', [
                    'display_name' => $displayName,
                    'entity_class' => get_class($entity),
                ]);
                return $displayName;
            }

            if (method_exists($entity, 'getTitle')) {
                $displayName = $entity->getTitle();
                $this->logger->debug('Entity display name from getTitle', [
                    'display_name' => $displayName,
                    'entity_class' => get_class($entity),
                ]);
                return $displayName;
            }

            if (method_exists($entity, 'getName')) {
                $displayName = $entity->getName();
                $this->logger->debug('Entity display name from getName', [
                    'display_name' => $displayName,
                    'entity_class' => get_class($entity),
                ]);
                return $displayName;
            }

            if (method_exists($entity, 'getId')) {
                $reflection = new ReflectionClass($entity);
                $displayName = $reflection->getShortName() . ' #' . $entity->getId();
                $this->logger->debug('Entity display name from class name + ID', [
                    'display_name' => $displayName,
                    'entity_class' => get_class($entity),
                ]);
                return $displayName;
            }

            $reflection = new ReflectionClass($entity);
            $displayName = $reflection->getShortName();
            $this->logger->debug('Entity display name from class name only', [
                'display_name' => $displayName,
                'entity_class' => get_class($entity),
            ]);
            return $displayName;

        } catch (ReflectionException $e) {
            $this->logger->error('Reflection error getting entity display name', [
                'entity_class' => get_class($entity),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 'Entité inconnue';

        } catch (Exception $e) {
            $this->logger->error('Error getting entity display name', [
                'entity_class' => get_class($entity),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 'Erreur d\'affichage';
        }
    }

    /**
     * Get a human-readable display name for an entity class.
     */
    private function getEntityClassDisplayName(string $entityClass): string
    {
        try {
            $this->logger->debug('Getting entity class display name', [
                'entity_class' => $entityClass,
            ]);

            $reflection = new ReflectionClass($entityClass);
            $shortName = $reflection->getShortName();

            $this->logger->debug('Entity class short name extracted', [
                'entity_class' => $entityClass,
                'short_name' => $shortName,
            ]);

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
                'Student' => 'Étudiants',
                'User' => 'Utilisateurs',
                'Prospect' => 'Prospects',
                'ProspectNote' => 'Notes de prospect',
                'ContactRequest' => 'Demandes de contact',
                'NeedsAnalysisRequest' => 'Demandes d\'analyse des besoins',
                'Service' => 'Services',
                'ServiceCategory' => 'Catégories de services',
                'Document' => 'Documents',
                'DocumentCategory' => 'Catégories de documents',
                'Questionnaire' => 'Questionnaires',
                'Question' => 'Questions',
                'AttendanceRecord' => 'Registres de présence',
                'Alternance' => 'Alternances',
                'AlternanceContract' => 'Contrats d\'alternance',
                'CompanyVisit' => 'Visites d\'entreprise',
                'MissionAssignment' => 'Affectations de mission',
                'SkillsAssessment' => 'Évaluations des compétences',
                'CoordinationMeeting' => 'Réunions de coordination',
                'Mentor' => 'Mentors',
                'CompanyMission' => 'Missions d\'entreprise',
                'StudentProgress' => 'Progrès des étudiants',
            ];

            $displayName = $displayNames[$shortName] ?? $shortName;

            $this->logger->debug('Entity class display name resolved', [
                'entity_class' => $entityClass,
                'short_name' => $shortName,
                'display_name' => $displayName,
                'was_mapped' => array_key_exists($shortName, $displayNames),
            ]);

            return $displayName;

        } catch (ReflectionException $e) {
            $this->logger->error('Reflection error getting entity class display name', [
                'entity_class' => $entityClass,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 'Entité inconnue';

        } catch (Exception $e) {
            $this->logger->error('Error getting entity class display name', [
                'entity_class' => $entityClass,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 'Erreur d\'affichage';
        }
    }
}
