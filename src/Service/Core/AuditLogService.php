<?php

declare(strict_types=1);

namespace App\Service\Core;

use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Gedmo\Loggable\Entity\LogEntry;
use Gedmo\Loggable\Entity\Repository\LogEntryRepository;
use Gedmo\Mapping\Annotation\Loggable;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;

/**
 * Service for managing audit logs of loggable entities.
 *
 * Provides functionality to retrieve and format change history
 * for entities that use Gedmo\Loggable extension.
 */
class AuditLogService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    /**
     * Get the change history for a specific entity.
     *
     * @param object $entity The entity to get history for
     * @param int $limit Maximum number of log entries to return
     *
     * @return LogEntry[]
     */
    public function getEntityHistory(object $entity, int $limit = 50): array
    {
        try {
            $entityClass = get_class($entity);
            $entityId = $this->getEntityId($entity);

            $this->logger->info('Retrieving entity history', [
                'entity_class' => $entityClass,
                'entity_id' => $entityId,
                'limit' => $limit,
            ]);

            if (!$this->isEntityLoggable($entityClass)) {
                $this->logger->warning('Attempted to get history for non-loggable entity', [
                    'entity_class' => $entityClass,
                    'entity_id' => $entityId,
                ]);
                return [];
            }

            /** @var LogEntryRepository $logRepo */
            $logRepo = $this->entityManager->getRepository(LogEntry::class);

            $this->logger->debug('Fetching log entries from repository', [
                'entity_class' => $entityClass,
                'entity_id' => $entityId,
                'limit' => $limit,
            ]);

            $logEntries = $logRepo->getLogEntries($entity, $limit);

            $this->logger->info('Entity history retrieved successfully', [
                'entity_class' => $entityClass,
                'entity_id' => $entityId,
                'entries_count' => count($logEntries),
                'limit' => $limit,
            ]);

            return $logEntries;

        } catch (ORMException $e) {
            $this->logger->error('Database error while retrieving entity history', [
                'entity_class' => get_class($entity),
                'entity_id' => $this->getEntityId($entity),
                'limit' => $limit,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);
            throw new \RuntimeException('Failed to retrieve entity history: ' . $e->getMessage(), 0, $e);

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error while retrieving entity history', [
                'entity_class' => get_class($entity),
                'entity_id' => $this->getEntityId($entity),
                'limit' => $limit,
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Get formatted change data for an entity.
     *
     * @param object $entity The entity to get changes for
     * @param int $limit Maximum number of log entries to return
     *
     * @return array Formatted change data with metadata
     */
    public function getFormattedEntityChanges(object $entity, int $limit = 50): array
    {
        try {
            $entityClass = get_class($entity);
            $entityId = $this->getEntityId($entity);

            $this->logger->info('Formatting entity changes', [
                'entity_class' => $entityClass,
                'entity_id' => $entityId,
                'limit' => $limit,
            ]);

            $logEntries = $this->getEntityHistory($entity, $limit);
            $formattedChanges = [];

            $this->logger->debug('Processing log entries for formatting', [
                'entity_class' => $entityClass,
                'entity_id' => $entityId,
                'entries_count' => count($logEntries),
            ]);

            foreach ($logEntries as $logEntry) {
                $this->logger->debug('Processing log entry', [
                    'entry_id' => $logEntry->getId(),
                    'version' => $logEntry->getVersion(),
                    'action' => $logEntry->getAction(),
                    'username' => $logEntry->getUsername(),
                    'logged_at' => $logEntry->getLoggedAt()?->format('Y-m-d H:i:s'),
                ]);

                $formattedChanges[] = [
                    'version' => $logEntry->getVersion(),
                    'action' => $logEntry->getAction(),
                    'username' => $logEntry->getUsername() ?: 'System',
                    'loggedAt' => $logEntry->getLoggedAt(),
                    'data' => $logEntry->getData(),
                    'objectClass' => $logEntry->getObjectClass(),
                    'objectId' => $logEntry->getObjectId(),
                ];
            }

            $this->logger->info('Entity changes formatted successfully', [
                'entity_class' => $entityClass,
                'entity_id' => $entityId,
                'formatted_changes_count' => count($formattedChanges),
            ]);

            return $formattedChanges;

        } catch (\Exception $e) {
            $this->logger->error('Error while formatting entity changes', [
                'entity_class' => get_class($entity),
                'entity_id' => $this->getEntityId($entity),
                'limit' => $limit,
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Compare two versions of an entity to show differences.
     *
     * @param array $oldData Previous version data
     * @param array $newData Current version data
     *
     * @return array Array of changes with field names and old/new values
     */
    public function compareVersions(array $oldData, array $newData): array
    {
        try {
            $this->logger->info('Comparing entity versions', [
                'old_data_fields' => count($oldData),
                'new_data_fields' => count($newData),
                'old_fields' => array_keys($oldData),
                'new_fields' => array_keys($newData),
            ]);

            $changes = [];

            // Find added or modified fields
            $this->logger->debug('Analyzing added or modified fields');
            foreach ($newData as $field => $newValue) {
                $oldValue = $oldData[$field] ?? null;

                if ($oldValue !== $newValue) {
                    $changeType = $oldValue === null ? 'added' : 'modified';
                    
                    $this->logger->debug('Field change detected', [
                        'field' => $field,
                        'change_type' => $changeType,
                        'old_value_type' => gettype($oldValue),
                        'new_value_type' => gettype($newValue),
                        'old_value_length' => is_string($oldValue) ? strlen($oldValue) : null,
                        'new_value_length' => is_string($newValue) ? strlen($newValue) : null,
                    ]);

                    $changes[$field] = [
                        'old' => $oldValue,
                        'new' => $newValue,
                        'type' => $changeType,
                    ];
                }
            }

            // Find removed fields
            $this->logger->debug('Analyzing removed fields');
            foreach ($oldData as $field => $oldValue) {
                if (!array_key_exists($field, $newData)) {
                    $this->logger->debug('Field removal detected', [
                        'field' => $field,
                        'old_value_type' => gettype($oldValue),
                        'old_value_length' => is_string($oldValue) ? strlen($oldValue) : null,
                    ]);

                    $changes[$field] = [
                        'old' => $oldValue,
                        'new' => null,
                        'type' => 'removed',
                    ];
                }
            }

            $this->logger->info('Version comparison completed', [
                'total_changes' => count($changes),
                'added_fields' => count(array_filter($changes, fn($change) => $change['type'] === 'added')),
                'modified_fields' => count(array_filter($changes, fn($change) => $change['type'] === 'modified')),
                'removed_fields' => count(array_filter($changes, fn($change) => $change['type'] === 'removed')),
                'changed_fields' => array_keys($changes),
            ]);

            return $changes;

        } catch (\Exception $e) {
            $this->logger->error('Error while comparing entity versions', [
                'old_data_fields' => count($oldData),
                'new_data_fields' => count($newData),
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Get human-readable field names for display.
     *
     * @param string $fieldName The technical field name
     * @param string $entityClass The entity class name
     *
     * @return string Human-readable field name
     */
    public function getHumanReadableFieldName(string $fieldName, string $entityClass): string
    {
        try {
            $this->logger->debug('Getting human-readable field name', [
                'field_name' => $fieldName,
                'entity_class' => $entityClass,
            ]);

            // Define field mappings for common entities
            $fieldMappings = [
            'Formation' => [
                'title' => 'Titre',
                'slug' => 'Slug',
                'description' => 'Description',
                'objectives' => 'Objectifs',
                'operationalObjectives' => 'Objectifs opérationnels',
                'evaluableObjectives' => 'Objectifs évaluables',
                'evaluationCriteria' => 'Critères d\'évaluation',
                'successIndicators' => 'Indicateurs de réussite',
                'prerequisites' => 'Prérequis',
                'durationHours' => 'Durée (heures)',
                'price' => 'Prix',
                'level' => 'Niveau',
                'format' => 'Format',
                'isActive' => 'Actif',
                'isFeatured' => 'Mis en avant',
                'targetAudience' => 'Public cible',
                'accessModalities' => 'Modalités d\'accès',
                'handicapAccessibility' => 'Accessibilité handicap',
                'teachingMethods' => 'Méthodes pédagogiques',
                'evaluationMethods' => 'Méthodes d\'évaluation',
                'contactInfo' => 'Informations de contact',
                'trainingLocation' => 'Lieu de formation',
                'fundingModalities' => 'Modalités de financement',
            ],
            'Category' => [
                'name' => 'Nom',
                'slug' => 'Slug',
                'description' => 'Description',
                'icon' => 'Icône',
                'isActive' => 'Actif',
            ],
            'Module' => [
                'title' => 'Titre',
                'slug' => 'Slug',
                'description' => 'Description',
                'learningObjectives' => 'Objectifs d\'apprentissage',
                'prerequisites' => 'Prérequis',
                'durationHours' => 'Durée (heures)',
                'orderIndex' => 'Ordre',
                'evaluationMethods' => 'Méthodes d\'évaluation',
                'teachingMethods' => 'Méthodes pédagogiques',
                'resources' => 'Ressources',
                'successCriteria' => 'Critères de réussite',
                'isActive' => 'Actif',
            ],
            'Chapter' => [
                'title' => 'Titre',
                'slug' => 'Slug',
                'description' => 'Description',
                'learningObjectives' => 'Objectifs d\'apprentissage',
                'contentOutline' => 'Plan du contenu',
                'prerequisites' => 'Prérequis',
                'learningOutcomes' => 'Résultats d\'apprentissage',
                'teachingMethods' => 'Méthodes pédagogiques',
                'resources' => 'Ressources',
                'assessmentMethods' => 'Méthodes d\'évaluation',
                'successCriteria' => 'Critères de réussite',
                'durationMinutes' => 'Durée (minutes)',
                'orderIndex' => 'Ordre',
                'isActive' => 'Actif',
            ],
            'Course' => [
                'title' => 'Titre',
                'slug' => 'Slug',
                'description' => 'Description',
                'content' => 'Contenu',
                'type' => 'Type',
                'learningObjectives' => 'Objectifs d\'apprentissage',
                'contentOutline' => 'Plan du contenu',
                'prerequisites' => 'Prérequis',
                'learningOutcomes' => 'Résultats d\'apprentissage',
                'teachingMethods' => 'Méthodes pédagogiques',
                'resources' => 'Ressources',
                'assessmentMethods' => 'Méthodes d\'évaluation',
                'successCriteria' => 'Critères de réussite',
                'durationMinutes' => 'Durée (minutes)',
                'orderIndex' => 'Ordre',
                'isActive' => 'Actif',
            ],
            'Session' => [
                'name' => 'Nom',
                'description' => 'Description',
                'startDate' => 'Date de début',
                'endDate' => 'Date de fin',
                'registrationDeadline' => 'Date limite d\'inscription',
                'location' => 'Lieu',
                'address' => 'Adresse',
                'maxCapacity' => 'Capacité maximale',
                'minCapacity' => 'Capacité minimale',
                'price' => 'Prix',
                'status' => 'Statut',
                'instructor' => 'Formateur',
                'notes' => 'Notes',
                'isActive' => 'Actif',
            ],
            'Exercise' => [
                'title' => 'Titre',
                'slug' => 'Slug',
                'description' => 'Description',
                'instructions' => 'Instructions',
                'expectedOutcomes' => 'Résultats attendus',
                'evaluationCriteria' => 'Critères d\'évaluation',
                'resources' => 'Ressources',
                'prerequisites' => 'Prérequis',
                'successCriteria' => 'Critères de réussite',
                'type' => 'Type',
                'difficulty' => 'Difficulté',
                'estimatedDurationMinutes' => 'Durée estimée (minutes)',
                'maxPoints' => 'Points maximum',
                'passingPoints' => 'Points de réussite',
                'orderIndex' => 'Ordre',
                'isActive' => 'Actif',
            ],
            'QCM' => [
                'title' => 'Titre',
                'slug' => 'Slug',
                'description' => 'Description',
                'instructions' => 'Instructions',
                'questions' => 'Questions',
                'evaluationCriteria' => 'Critères d\'évaluation',
                'successCriteria' => 'Critères de réussite',
                'timeLimitMinutes' => 'Limite de temps (minutes)',
                'maxScore' => 'Score maximum',
                'passingScore' => 'Score de réussite',
                'maxAttempts' => 'Tentatives autorisées',
                'showCorrectAnswers' => 'Afficher les bonnes réponses',
                'showExplanations' => 'Afficher les explications',
                'randomizeQuestions' => 'Questions aléatoires',
                'randomizeAnswers' => 'Réponses aléatoires',
                'orderIndex' => 'Ordre',
                'isActive' => 'Actif',
            ],
            ];

            $shortClassName = (new ReflectionClass($entityClass))->getShortName();

            $humanReadableName = $fieldMappings[$shortClassName][$fieldName] ?? ucfirst($fieldName);

            $this->logger->debug('Human-readable field name resolved', [
                'field_name' => $fieldName,
                'entity_class' => $entityClass,
                'short_class_name' => $shortClassName,
                'human_readable_name' => $humanReadableName,
                'mapping_found' => isset($fieldMappings[$shortClassName][$fieldName]),
            ]);

            return $humanReadableName;

        } catch (ReflectionException $e) {
            $this->logger->error('Reflection error while getting human-readable field name', [
                'field_name' => $fieldName,
                'entity_class' => $entityClass,
                'error_message' => $e->getMessage(),
            ]);
            return ucfirst($fieldName);

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error while getting human-readable field name', [
                'field_name' => $fieldName,
                'entity_class' => $entityClass,
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'stack_trace' => $e->getTraceAsString(),
            ]);
            return ucfirst($fieldName);
        }
    }    /**
     * Format a field value for display.
     *
     * @param mixed $value The field value
     * @param string $fieldName The field name for context
     *
     * @return string Formatted value
     */
    public function formatFieldValue($value, string $fieldName): string
    {
        try {
            $this->logger->debug('Formatting field value', [
                'field_name' => $fieldName,
                'value_type' => gettype($value),
                'value_length' => is_string($value) ? strlen($value) : null,
                'is_null' => $value === null,
                'is_array' => is_array($value),
                'is_object' => is_object($value),
            ]);

            if ($value === null) {
                $this->logger->debug('Formatting null value');
                return '<em class="text-muted">null</em>';
            }

            if (is_bool($value)) {
                $this->logger->debug('Formatting boolean value', ['value' => $value]);
                return $value ? '<span class="badge bg-success">Oui</span>' : '<span class="badge bg-danger">Non</span>';
            }

            if (is_array($value)) {
                $this->logger->debug('Formatting array value', [
                    'array_count' => count($value),
                    'is_empty' => empty($value),
                ]);

                if (empty($value)) {
                    return '<em class="text-muted">vide</em>';
                }

                return '<ul class="mb-0">' . implode('', array_map(static fn ($item) => '<li>' . htmlspecialchars((string) $item) . '</li>', $value)) . '</ul>';
            }

            if ($value instanceof DateTimeInterface) {
                $this->logger->debug('Formatting DateTime value', [
                    'datetime' => $value->format('Y-m-d H:i:s'),
                ]);
                return $value->format('d/m/Y H:i:s');
            }

            if (is_string($value) && strlen($value) > 100) {
                $this->logger->debug('Formatting long string value', [
                    'string_length' => strlen($value),
                ]);
                return '<details><summary>Afficher le contenu (' . strlen($value) . ' caractères)</summary><div class="mt-2">' . nl2br(htmlspecialchars($value)) . '</div></details>';
            }

            $this->logger->debug('Formatting string/scalar value', [
                'value_length' => is_string($value) ? strlen($value) : null,
            ]);

            return htmlspecialchars((string) $value);

        } catch (\Exception $e) {
            $this->logger->error('Error while formatting field value', [
                'field_name' => $fieldName,
                'value_type' => gettype($value),
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);
            
            // Return a safe fallback
            return '<em class="text-muted">Erreur de formatage</em>';
        }
    }

    /**
     * Check if an entity class is loggable.
     *
     * @param string $entityClass The entity class name
     *
     * @return bool True if the entity is loggable
     */
    public function isEntityLoggable(string $entityClass): bool
    {
        try {
            $this->logger->debug('Checking if entity is loggable', [
                'entity_class' => $entityClass,
            ]);

            $reflectionClass = new ReflectionClass($entityClass);
            $attributes = $reflectionClass->getAttributes(Loggable::class);

            $isLoggable = !empty($attributes);

            $this->logger->debug('Entity loggable check completed', [
                'entity_class' => $entityClass,
                'is_loggable' => $isLoggable,
                'attributes_count' => count($attributes),
            ]);

            return $isLoggable;

        } catch (ReflectionException $e) {
            $this->logger->error('Reflection error while checking if entity is loggable', [
                'entity_class' => $entityClass,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);
            return false;

        } catch (\Exception $e) {
            $this->logger->error('Unexpected error while checking if entity is loggable', [
                'entity_class' => $entityClass,
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Get all loggable entity classes.
     *
     * @return array Array of loggable entity class names
     */
    public function getLoggableEntities(): array
    {
        try {
            $this->logger->info('Retrieving all loggable entities');

            $loggableEntities = [];
            $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

            $this->logger->debug('Processing entity metadata', [
                'total_entities' => count($metadata),
            ]);

            foreach ($metadata as $classMetadata) {
                $className = $classMetadata->getName();
                
                $this->logger->debug('Checking entity for loggable attribute', [
                    'entity_class' => $className,
                ]);

                if ($this->isEntityLoggable($className)) {
                    $loggableEntities[] = $className;
                    
                    $this->logger->debug('Found loggable entity', [
                        'entity_class' => $className,
                    ]);
                }
            }

            $this->logger->info('Loggable entities retrieval completed', [
                'total_entities_checked' => count($metadata),
                'loggable_entities_found' => count($loggableEntities),
                'loggable_entities' => $loggableEntities,
            ]);

            return $loggableEntities;

        } catch (\Exception $e) {
            $this->logger->error('Error while retrieving loggable entities', [
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Get the ID of an entity.
     *
     * @param object $entity The entity to get ID for
     *
     * @return mixed The entity ID
     */
    private function getEntityId(object $entity): mixed
    {
        try {
            $metadata = $this->entityManager->getClassMetadata(get_class($entity));
            $identifierValues = $metadata->getIdentifierValues($entity);

            if (empty($identifierValues)) {
                return null;
            }

            // Return the first identifier value (most entities have single primary key)
            return reset($identifierValues);

        } catch (\Exception $e) {
            $this->logger->warning('Failed to get entity ID', [
                'entity_class' => get_class($entity),
                'error_message' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
