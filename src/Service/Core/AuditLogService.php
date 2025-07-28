<?php

declare(strict_types=1);

namespace App\Service\Core;

use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Loggable\Entity\LogEntry;
use Gedmo\Loggable\Entity\Repository\LogEntryRepository;
use Gedmo\Mapping\Annotation\Loggable;
use ReflectionClass;

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
        /** @var LogEntryRepository $logRepo */
        $logRepo = $this->entityManager->getRepository(LogEntry::class);

        return $logRepo->getLogEntries($entity, $limit);
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
        $logEntries = $this->getEntityHistory($entity, $limit);
        $formattedChanges = [];

        foreach ($logEntries as $logEntry) {
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

        return $formattedChanges;
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
        $changes = [];

        // Find added or modified fields
        foreach ($newData as $field => $newValue) {
            $oldValue = $oldData[$field] ?? null;

            if ($oldValue !== $newValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                    'type' => $oldValue === null ? 'added' : 'modified',
                ];
            }
        }

        // Find removed fields
        foreach ($oldData as $field => $oldValue) {
            if (!array_key_exists($field, $newData)) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => null,
                    'type' => 'removed',
                ];
            }
        }

        return $changes;
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

        return $fieldMappings[$shortClassName][$fieldName] ?? ucfirst($fieldName);
    }

    /**
     * Format a field value for display.
     *
     * @param mixed $value The field value
     * @param string $fieldName The field name for context
     *
     * @return string Formatted value
     */
    public function formatFieldValue($value, string $fieldName): string
    {
        if ($value === null) {
            return '<em class="text-muted">null</em>';
        }

        if (is_bool($value)) {
            return $value ? '<span class="badge bg-success">Oui</span>' : '<span class="badge bg-danger">Non</span>';
        }

        if (is_array($value)) {
            if (empty($value)) {
                return '<em class="text-muted">vide</em>';
            }

            return '<ul class="mb-0">' . implode('', array_map(static fn ($item) => '<li>' . htmlspecialchars((string) $item) . '</li>', $value)) . '</ul>';
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('d/m/Y H:i:s');
        }

        if (is_string($value) && strlen($value) > 100) {
            return '<details><summary>Afficher le contenu (' . strlen($value) . ' caractères)</summary><div class="mt-2">' . nl2br(htmlspecialchars($value)) . '</div></details>';
        }

        return htmlspecialchars((string) $value);
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
        $reflectionClass = new ReflectionClass($entityClass);
        $attributes = $reflectionClass->getAttributes(Loggable::class);

        return !empty($attributes);
    }

    /**
     * Get all loggable entity classes.
     *
     * @return array Array of loggable entity class names
     */
    public function getLoggableEntities(): array
    {
        $loggableEntities = [];
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();

        foreach ($metadata as $classMetadata) {
            $className = $classMetadata->getName();
            if ($this->isEntityLoggable($className)) {
                $loggableEntities[] = $className;
            }
        }

        return $loggableEntities;
    }
}
