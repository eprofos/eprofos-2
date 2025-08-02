<?php

declare(strict_types=1);

namespace App\Service\Document;

use App\Entity\Document\DocumentType;
use App\Repository\Document\DocumentTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Document Type Service.
 *
 * Provides business logic for managing document types.
 * Handles type creation, validation, and business rules.
 */
class DocumentTypeService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DocumentTypeRepository $documentTypeRepository,
        private SluggerInterface $slugger,
        private LoggerInterface $logger,
    ) {}

    /**
     * Create a new document type with automatic slug generation.
     */
    public function createDocumentType(DocumentType $documentType): array
    {
        $this->logger->info('Starting document type creation', [
            'name' => $documentType->getName(),
            'code' => $documentType->getCode(),
            'is_active' => $documentType->isActive(),
            'allow_multiple_published' => $documentType->isAllowMultiplePublished(),
        ]);

        try {
            // Validate input data
            $this->logger->debug('Validating document type data', [
                'name' => $documentType->getName(),
                'code' => $documentType->getCode(),
                'description' => $documentType->getDescription(),
            ]);

            if (empty($documentType->getName())) {
                $this->logger->warning('Document type creation failed: empty name');
                return [
                    'success' => false,
                    'error' => 'Le nom du type de document est obligatoire.',
                ];
            }

            // Generate unique code if not provided
            if (!$documentType->getCode()) {
                $this->logger->debug('Generating unique code for document type', [
                    'name' => $documentType->getName(),
                ]);
                
                try {
                    $code = $this->generateUniqueCode($documentType->getName());
                    $documentType->setCode($code);
                    
                    $this->logger->info('Generated unique code for document type', [
                        'name' => $documentType->getName(),
                        'generated_code' => $code,
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Failed to generate unique code', [
                        'name' => $documentType->getName(),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    
                    return [
                        'success' => false,
                        'error' => 'Erreur lors de la génération du code unique: ' . $e->getMessage(),
                    ];
                }
            }

            // Validate uniqueness
            $this->logger->debug('Checking code uniqueness', [
                'code' => $documentType->getCode(),
            ]);

            try {
                $existing = $this->documentTypeRepository->findByCode($documentType->getCode());
                if ($existing && $existing->getId() !== $documentType->getId()) {
                    $this->logger->warning('Document type creation failed: code already exists', [
                        'code' => $documentType->getCode(),
                        'existing_id' => $existing->getId(),
                        'existing_name' => $existing->getName(),
                    ]);
                    
                    return [
                        'success' => false,
                        'error' => 'Un type de document avec ce code existe déjà.',
                    ];
                }
            } catch (Exception $e) {
                $this->logger->error('Error checking code uniqueness', [
                    'code' => $documentType->getCode(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                return [
                    'success' => false,
                    'error' => 'Erreur lors de la vérification de l\'unicité du code: ' . $e->getMessage(),
                ];
            }

            // Set default configuration if not provided
            if (!$documentType->getConfiguration()) {
                $defaultConfig = [
                    'auto_version' => true,
                    'track_downloads' => true,
                    'enable_comments' => false,
                    'require_review' => false,
                ];
                
                $documentType->setConfiguration($defaultConfig);
                
                $this->logger->debug('Applied default configuration', [
                    'code' => $documentType->getCode(),
                    'configuration' => $defaultConfig,
                ]);
            }

            // Set default allowed statuses if not provided
            if (!$documentType->getAllowedStatuses()) {
                $defaultStatuses = [
                    'draft',
                    'under_review',
                    'published',
                    'archived',
                ];
                
                $documentType->setAllowedStatuses($defaultStatuses);
                
                $this->logger->debug('Applied default allowed statuses', [
                    'code' => $documentType->getCode(),
                    'statuses' => $defaultStatuses,
                ]);
            }

            // Set sort order if not provided
            if (!$documentType->getSortOrder()) {
                try {
                    $sortOrder = $this->getNextSortOrder();
                    $documentType->setSortOrder($sortOrder);
                    
                    $this->logger->debug('Set sort order for document type', [
                        'code' => $documentType->getCode(),
                        'sort_order' => $sortOrder,
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Failed to set sort order', [
                        'code' => $documentType->getCode(),
                        'error' => $e->getMessage(),
                    ]);
                    // Continue anyway, sort order is not critical
                }
            }

            // Persist and flush
            $this->logger->debug('Persisting document type to database', [
                'code' => $documentType->getCode(),
                'name' => $documentType->getName(),
            ]);

            try {
                $this->entityManager->persist($documentType);
                $this->entityManager->flush();
                
                $this->logger->info('Document type successfully created', [
                    'type_id' => $documentType->getId(),
                    'code' => $documentType->getCode(),
                    'name' => $documentType->getName(),
                    'configuration' => $documentType->getConfiguration(),
                    'allowed_statuses' => $documentType->getAllowedStatuses(),
                    'sort_order' => $documentType->getSortOrder(),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Database error during document type creation', [
                    'code' => $documentType->getCode(),
                    'name' => $documentType->getName(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                return [
                    'success' => false,
                    'error' => 'Erreur lors de la sauvegarde en base de données: ' . $e->getMessage(),
                ];
            }

            return [
                'success' => true,
                'document_type' => $documentType,
            ];
        } catch (Exception $e) {
            $this->logger->error('Unexpected error creating document type', [
                'error' => $e->getMessage(),
                'code' => $documentType->getCode() ?? 'null',
                'name' => $documentType->getName() ?? 'null',
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return [
                'success' => false,
                'error' => 'Erreur inattendue lors de la création du type de document: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Update an existing document type.
     */
    public function updateDocumentType(DocumentType $documentType): array
    {
        $this->logger->info('Starting document type update', [
            'type_id' => $documentType->getId(),
            'name' => $documentType->getName(),
            'code' => $documentType->getCode(),
        ]);

        try {
            // Validate input data
            $this->logger->debug('Validating document type update data', [
                'type_id' => $documentType->getId(),
                'name' => $documentType->getName(),
                'code' => $documentType->getCode(),
                'description' => $documentType->getDescription(),
                'is_active' => $documentType->isActive(),
            ]);

            if (empty($documentType->getName())) {
                $this->logger->warning('Document type update failed: empty name', [
                    'type_id' => $documentType->getId(),
                ]);
                
                return [
                    'success' => false,
                    'error' => 'Le nom du type de document est obligatoire.',
                ];
            }

            if (empty($documentType->getCode())) {
                $this->logger->warning('Document type update failed: empty code', [
                    'type_id' => $documentType->getId(),
                ]);
                
                return [
                    'success' => false,
                    'error' => 'Le code du type de document est obligatoire.',
                ];
            }

            // Validate uniqueness if code changed
            $this->logger->debug('Checking code uniqueness for update', [
                'type_id' => $documentType->getId(),
                'code' => $documentType->getCode(),
            ]);

            try {
                $existing = $this->documentTypeRepository->findByCode($documentType->getCode());
                if ($existing && $existing->getId() !== $documentType->getId()) {
                    $this->logger->warning('Document type update failed: code already exists', [
                        'type_id' => $documentType->getId(),
                        'code' => $documentType->getCode(),
                        'existing_id' => $existing->getId(),
                        'existing_name' => $existing->getName(),
                    ]);
                    
                    return [
                        'success' => false,
                        'error' => 'Un type de document avec ce code existe déjà.',
                    ];
                }
            } catch (Exception $e) {
                $this->logger->error('Error checking code uniqueness during update', [
                    'type_id' => $documentType->getId(),
                    'code' => $documentType->getCode(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                return [
                    'success' => false,
                    'error' => 'Erreur lors de la vérification de l\'unicité du code: ' . $e->getMessage(),
                ];
            }

            // Validate business rules
            $this->logger->debug('Validating business rules for document type update', [
                'type_id' => $documentType->getId(),
            ]);

            try {
                $validation = $this->validateDocumentType($documentType);
                if (!$validation['is_valid']) {
                    $this->logger->warning('Document type update failed validation', [
                        'type_id' => $documentType->getId(),
                        'validation_issues' => $validation['issues'],
                    ]);
                    
                    return [
                        'success' => false,
                        'error' => 'Erreurs de validation: ' . implode(', ', $validation['issues']),
                    ];
                }
            } catch (Exception $e) {
                $this->logger->error('Error during business rules validation', [
                    'type_id' => $documentType->getId(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                return [
                    'success' => false,
                    'error' => 'Erreur lors de la validation: ' . $e->getMessage(),
                ];
            }

            // Update timestamp
            $documentType->setUpdatedAt(new \DateTimeImmutable());

            // Flush changes to database
            $this->logger->debug('Flushing document type changes to database', [
                'type_id' => $documentType->getId(),
                'code' => $documentType->getCode(),
            ]);

            try {
                $this->entityManager->flush();
                
                $this->logger->info('Document type successfully updated', [
                    'type_id' => $documentType->getId(),
                    'code' => $documentType->getCode(),
                    'name' => $documentType->getName(),
                    'configuration' => $documentType->getConfiguration(),
                    'allowed_statuses' => $documentType->getAllowedStatuses(),
                    'is_active' => $documentType->isActive(),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Database error during document type update', [
                    'type_id' => $documentType->getId(),
                    'code' => $documentType->getCode(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                return [
                    'success' => false,
                    'error' => 'Erreur lors de la sauvegarde en base de données: ' . $e->getMessage(),
                ];
            }

            return [
                'success' => true,
                'document_type' => $documentType,
            ];
        } catch (Exception $e) {
            $this->logger->error('Unexpected error updating document type', [
                'error' => $e->getMessage(),
                'type_id' => $documentType->getId() ?? 'null',
                'code' => $documentType->getCode() ?? 'null',
                'name' => $documentType->getName() ?? 'null',
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return [
                'success' => false,
                'error' => 'Erreur inattendue lors de la mise à jour du type de document: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Delete a document type (only if no documents exist).
     */
    public function deleteDocumentType(DocumentType $documentType): array
    {
        $this->logger->info('Starting document type deletion', [
            'type_id' => $documentType->getId(),
            'code' => $documentType->getCode(),
            'name' => $documentType->getName(),
        ]);

        try {
            // Check if type has documents
            $this->logger->debug('Checking if document type has associated documents', [
                'type_id' => $documentType->getId(),
                'code' => $documentType->getCode(),
            ]);

            try {
                $documentCount = $documentType->getDocuments()->count();
                
                $this->logger->debug('Document count check completed', [
                    'type_id' => $documentType->getId(),
                    'document_count' => $documentCount,
                ]);

                if ($documentCount > 0) {
                    $this->logger->warning('Document type deletion blocked: has associated documents', [
                        'type_id' => $documentType->getId(),
                        'code' => $documentType->getCode(),
                        'document_count' => $documentCount,
                    ]);
                    
                    return [
                        'success' => false,
                        'error' => 'Impossible de supprimer ce type car il contient des documents.',
                    ];
                }
            } catch (Exception $e) {
                $this->logger->error('Error checking associated documents', [
                    'type_id' => $documentType->getId(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                return [
                    'success' => false,
                    'error' => 'Erreur lors de la vérification des documents associés: ' . $e->getMessage(),
                ];
            }

            // Check if type has templates
            $this->logger->debug('Checking if document type has associated templates', [
                'type_id' => $documentType->getId(),
                'code' => $documentType->getCode(),
            ]);

            try {
                $templateCount = $documentType->getTemplates()->count();
                
                $this->logger->debug('Template count check completed', [
                    'type_id' => $documentType->getId(),
                    'template_count' => $templateCount,
                ]);

                if ($templateCount > 0) {
                    $this->logger->warning('Document type deletion blocked: has associated templates', [
                        'type_id' => $documentType->getId(),
                        'code' => $documentType->getCode(),
                        'template_count' => $templateCount,
                    ]);
                    
                    return [
                        'success' => false,
                        'error' => 'Impossible de supprimer ce type car il contient des modèles.',
                    ];
                }
            } catch (Exception $e) {
                $this->logger->error('Error checking associated templates', [
                    'type_id' => $documentType->getId(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                return [
                    'success' => false,
                    'error' => 'Erreur lors de la vérification des modèles associés: ' . $e->getMessage(),
                ];
            }

            // Store information before deletion
            $typeId = $documentType->getId();
            $typeName = $documentType->getName();
            $typeCode = $documentType->getCode();

            // Perform deletion
            $this->logger->debug('Removing document type from database', [
                'type_id' => $typeId,
                'code' => $typeCode,
                'name' => $typeName,
            ]);

            try {
                $this->entityManager->remove($documentType);
                $this->entityManager->flush();
                
                $this->logger->info('Document type successfully deleted', [
                    'type_id' => $typeId,
                    'code' => $typeCode,
                    'name' => $typeName,
                ]);
            } catch (Exception $e) {
                $this->logger->error('Database error during document type deletion', [
                    'type_id' => $typeId,
                    'code' => $typeCode,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                return [
                    'success' => false,
                    'error' => 'Erreur lors de la suppression en base de données: ' . $e->getMessage(),
                ];
            }

            return [
                'success' => true,
            ];
        } catch (Exception $e) {
            $this->logger->error('Unexpected error deleting document type', [
                'error' => $e->getMessage(),
                'type_id' => $documentType->getId() ?? 'null',
                'code' => $documentType->getCode() ?? 'null',
                'name' => $documentType->getName() ?? 'null',
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return [
                'success' => false,
                'error' => 'Erreur inattendue lors de la suppression du type de document: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get document types with statistics.
     */
    public function getDocumentTypesWithStats(): array
    {
        $this->logger->info('Fetching document types with statistics');

        try {
            $this->logger->debug('Retrieving all active document types');
            
            try {
                $types = $this->documentTypeRepository->findAllActive();
                
                $this->logger->debug('Active document types retrieved', [
                    'count' => count($types),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Error retrieving active document types', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                throw new Exception('Erreur lors de la récupération des types de documents: ' . $e->getMessage());
            }

            $stats = [];

            foreach ($types as $index => $type) {
                $this->logger->debug('Processing statistics for document type', [
                    'type_id' => $type->getId(),
                    'code' => $type->getCode(),
                    'name' => $type->getName(),
                    'index' => $index,
                ]);

                try {
                    $documentCount = $type->getDocuments()->count();
                    $templateCount = $type->getTemplates()->count();
                    $publishedCount = $type->getDocuments()->filter(
                        static fn ($doc) => $doc->getStatus() === 'published'
                    )->count();

                    $typeStats = [
                        'type' => $type,
                        'document_count' => $documentCount,
                        'template_count' => $templateCount,
                        'published_count' => $publishedCount,
                    ];

                    $stats[] = $typeStats;

                    $this->logger->debug('Statistics calculated for document type', [
                        'type_id' => $type->getId(),
                        'code' => $type->getCode(),
                        'document_count' => $documentCount,
                        'template_count' => $templateCount,
                        'published_count' => $publishedCount,
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Error calculating statistics for document type', [
                        'type_id' => $type->getId(),
                        'code' => $type->getCode(),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    // Continue with partial stats
                    $stats[] = [
                        'type' => $type,
                        'document_count' => 0,
                        'template_count' => 0,
                        'published_count' => 0,
                        'error' => 'Erreur lors du calcul des statistiques',
                    ];
                }
            }

            $this->logger->info('Document types statistics successfully calculated', [
                'total_types' => count($stats),
                'total_documents' => array_sum(array_column($stats, 'document_count')),
                'total_templates' => array_sum(array_column($stats, 'template_count')),
                'total_published' => array_sum(array_column($stats, 'published_count')),
            ]);

            return $stats;
        } catch (Exception $e) {
            $this->logger->error('Unexpected error getting document types with statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw new Exception('Erreur inattendue lors de la récupération des statistiques: ' . $e->getMessage());
        }
    }

    /**
     * Validate document type business rules.
     */
    public function validateDocumentType(DocumentType $documentType): array
    {
        $this->logger->debug('Starting document type validation', [
            'type_id' => $documentType->getId(),
            'code' => $documentType->getCode(),
            'name' => $documentType->getName(),
        ]);

        try {
            $issues = [];

            // Required fields validation
            $this->logger->debug('Validating required fields');

            if (!$documentType->getName()) {
                $issues[] = 'Le nom est obligatoire.';
                $this->logger->debug('Validation issue: missing name');
            }

            if (!$documentType->getCode()) {
                $issues[] = 'Le code est obligatoire.';
                $this->logger->debug('Validation issue: missing code');
            }

            // Code format validation
            if ($documentType->getCode()) {
                $this->logger->debug('Validating code format', [
                    'code' => $documentType->getCode(),
                ]);

                if (!preg_match('/^[a-z0-9_]+$/', $documentType->getCode())) {
                    $issues[] = 'Le code ne peut contenir que des lettres minuscules, chiffres et underscores.';
                    $this->logger->debug('Validation issue: invalid code format', [
                        'code' => $documentType->getCode(),
                    ]);
                }
            }

            // Business logic validation
            $this->logger->debug('Validating business logic rules');

            try {
                if (!$documentType->isAllowMultiplePublished() && $documentType->getDocuments()->count() > 1) {
                    $publishedDocs = $documentType->getDocuments()->filter(
                        static fn ($doc) => $doc->getStatus() === 'published'
                    );
                    $publishedCount = $publishedDocs->count();

                    $this->logger->debug('Checking multiple published documents rule', [
                        'type_id' => $documentType->getId(),
                        'allow_multiple_published' => $documentType->isAllowMultiplePublished(),
                        'total_documents' => $documentType->getDocuments()->count(),
                        'published_count' => $publishedCount,
                    ]);

                    if ($publishedCount > 1) {
                        $issues[] = 'Ce type ne permet qu\'un seul document publié à la fois.';
                        $this->logger->debug('Validation issue: multiple published documents not allowed', [
                            'published_count' => $publishedCount,
                        ]);
                    }
                }
            } catch (Exception $e) {
                $this->logger->error('Error during business logic validation', [
                    'type_id' => $documentType->getId(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                $issues[] = 'Erreur lors de la validation des règles métier: ' . $e->getMessage();
            }

            // Configuration validation
            if ($documentType->getConfiguration()) {
                $this->logger->debug('Validating configuration', [
                    'configuration' => $documentType->getConfiguration(),
                ]);

                $config = $documentType->getConfiguration();
                $requiredConfigKeys = ['auto_version', 'track_downloads', 'enable_comments', 'require_review'];

                foreach ($requiredConfigKeys as $key) {
                    if (!array_key_exists($key, $config)) {
                        $issues[] = "Configuration manquante: {$key}";
                        $this->logger->debug('Validation issue: missing configuration key', [
                            'missing_key' => $key,
                        ]);
                    }
                }
            }

            // Allowed statuses validation
            if ($documentType->getAllowedStatuses()) {
                $this->logger->debug('Validating allowed statuses', [
                    'allowed_statuses' => $documentType->getAllowedStatuses(),
                ]);

                $allowedStatuses = $documentType->getAllowedStatuses();
                $validStatuses = ['draft', 'under_review', 'published', 'archived'];

                foreach ($allowedStatuses as $status) {
                    if (!in_array($status, $validStatuses, true)) {
                        $issues[] = "Statut non valide: {$status}";
                        $this->logger->debug('Validation issue: invalid status', [
                            'invalid_status' => $status,
                            'valid_statuses' => $validStatuses,
                        ]);
                    }
                }
            }

            $isValid = empty($issues);

            $this->logger->debug('Document type validation completed', [
                'type_id' => $documentType->getId(),
                'is_valid' => $isValid,
                'issues_count' => count($issues),
                'issues' => $issues,
            ]);

            return [
                'is_valid' => $isValid,
                'issues' => $issues,
            ];
        } catch (Exception $e) {
            $this->logger->error('Unexpected error during document type validation', [
                'error' => $e->getMessage(),
                'type_id' => $documentType->getId() ?? 'null',
                'code' => $documentType->getCode() ?? 'null',
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return [
                'is_valid' => false,
                'issues' => ['Erreur inattendue lors de la validation: ' . $e->getMessage()],
            ];
        }
    }

    /**
     * Toggle document type active status.
     */
    public function toggleActiveStatus(DocumentType $documentType): array
    {
        $this->logger->info('Starting document type status toggle', [
            'type_id' => $documentType->getId(),
            'code' => $documentType->getCode(),
            'current_status' => $documentType->isActive(),
        ]);

        try {
            $oldStatus = $documentType->isActive();
            $newStatus = !$oldStatus;

            $this->logger->debug('Toggling active status', [
                'type_id' => $documentType->getId(),
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);

            // Check if deactivating would cause issues
            if ($newStatus === false) {
                $this->logger->debug('Checking consequences of deactivation', [
                    'type_id' => $documentType->getId(),
                ]);

                try {
                    $activeDocumentCount = $documentType->getDocuments()->filter(
                        static fn ($doc) => in_array($doc->getStatus(), ['published', 'under_review'], true)
                    )->count();

                    if ($activeDocumentCount > 0) {
                        $this->logger->warning('Cannot deactivate document type: has active documents', [
                            'type_id' => $documentType->getId(),
                            'active_document_count' => $activeDocumentCount,
                        ]);

                        return [
                            'success' => false,
                            'error' => "Impossible de désactiver ce type car il contient {$activeDocumentCount} document(s) actif(s).",
                        ];
                    }
                } catch (Exception $e) {
                    $this->logger->error('Error checking active documents during deactivation', [
                        'type_id' => $documentType->getId(),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    return [
                        'success' => false,
                        'error' => 'Erreur lors de la vérification des documents actifs: ' . $e->getMessage(),
                    ];
                }
            }

            // Update status
            $documentType->setIsActive($newStatus);
            $documentType->setUpdatedAt(new \DateTimeImmutable());

            // Flush to database
            $this->logger->debug('Saving status change to database', [
                'type_id' => $documentType->getId(),
                'new_status' => $newStatus,
            ]);

            try {
                $this->entityManager->flush();

                $status = $documentType->isActive() ? 'activé' : 'désactivé';

                $this->logger->info('Document type status successfully toggled', [
                    'type_id' => $documentType->getId(),
                    'code' => $documentType->getCode(),
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'status_text' => $status,
                ]);

                return [
                    'success' => true,
                    'message' => "Le type de document a été {$status} avec succès.",
                ];
            } catch (Exception $e) {
                $this->logger->error('Database error during status toggle', [
                    'type_id' => $documentType->getId(),
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return [
                    'success' => false,
                    'error' => 'Erreur lors de la sauvegarde du changement de statut: ' . $e->getMessage(),
                ];
            }
        } catch (Exception $e) {
            $this->logger->error('Unexpected error toggling document type status', [
                'error' => $e->getMessage(),
                'type_id' => $documentType->getId() ?? 'null',
                'code' => $documentType->getCode() ?? 'null',
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return [
                'success' => false,
                'error' => 'Erreur inattendue lors du changement de statut: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get next sort order for document types.
     */
    public function getNextSortOrder(): int
    {
        $this->logger->debug('Calculating next sort order for document types');

        try {
            $maxOrder = $this->entityManager->createQueryBuilder()
                ->select('MAX(dt.sortOrder)')
                ->from(DocumentType::class, 'dt')
                ->getQuery()
                ->getSingleScalarResult()
            ;

            $nextOrder = ($maxOrder ?? 0) + 1;

            $this->logger->debug('Next sort order calculated', [
                'max_order' => $maxOrder,
                'next_order' => $nextOrder,
            ]);

            return $nextOrder;
        } catch (Exception $e) {
            $this->logger->error('Error calculating next sort order', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return default value in case of error
            $defaultOrder = 1;
            $this->logger->warning('Using default sort order due to error', [
                'default_order' => $defaultOrder,
            ]);

            return $defaultOrder;
        }
    }

    /**
     * Generate unique code from name.
     */
    private function generateUniqueCode(string $name): string
    {
        $this->logger->debug('Generating unique code from name', [
            'name' => $name,
        ]);

        try {
            $baseCode = (string) $this->slugger->slug($name)->lower();
            $code = $baseCode;
            $counter = 1;

            $this->logger->debug('Base code generated', [
                'name' => $name,
                'base_code' => $baseCode,
            ]);

            while ($this->documentTypeRepository->findByCode($code)) {
                $code = $baseCode . '_' . $counter;
                $counter++;

                $this->logger->debug('Code already exists, trying with counter', [
                    'existing_code' => $baseCode,
                    'new_code' => $code,
                    'counter' => $counter - 1,
                ]);

                // Prevent infinite loop
                if ($counter > 1000) {
                    $this->logger->error('Too many attempts to generate unique code', [
                        'name' => $name,
                        'base_code' => $baseCode,
                        'attempts' => $counter,
                    ]);
                    
                    throw new Exception('Impossible de générer un code unique après 1000 tentatives');
                }
            }

            $this->logger->info('Unique code successfully generated', [
                'name' => $name,
                'base_code' => $baseCode,
                'final_code' => $code,
                'attempts' => $counter - 1,
            ]);

            return $code;
        } catch (Exception $e) {
            $this->logger->error('Error generating unique code', [
                'name' => $name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new Exception('Erreur lors de la génération du code unique: ' . $e->getMessage());
        }
    }

    /**
     * Bulk update document types with detailed logging.
     */
    public function bulkUpdateDocumentTypes(array $documentTypes, array $updates): array
    {
        $this->logger->info('Starting bulk update of document types', [
            'type_count' => count($documentTypes),
            'updates' => array_keys($updates),
        ]);

        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
            'processed' => [],
        ];

        try {
            $this->entityManager->beginTransaction();

            foreach ($documentTypes as $index => $documentType) {
                $this->logger->debug('Processing document type in bulk update', [
                    'index' => $index,
                    'type_id' => $documentType->getId(),
                    'code' => $documentType->getCode(),
                ]);

                try {
                    // Apply updates
                    foreach ($updates as $field => $value) {
                        $this->logger->debug('Applying field update', [
                            'type_id' => $documentType->getId(),
                            'field' => $field,
                            'value' => $value,
                        ]);

                        switch ($field) {
                            case 'is_active':
                                $documentType->setIsActive((bool) $value);
                                break;
                            case 'configuration':
                                if (is_array($value)) {
                                    $documentType->setConfiguration($value);
                                }
                                break;
                            case 'allowed_statuses':
                                if (is_array($value)) {
                                    $documentType->setAllowedStatuses($value);
                                }
                                break;
                            default:
                                $this->logger->warning('Unknown field in bulk update', [
                                    'field' => $field,
                                    'type_id' => $documentType->getId(),
                                ]);
                        }
                    }

                    // Validate changes
                    $validation = $this->validateDocumentType($documentType);
                    if (!$validation['is_valid']) {
                        throw new Exception('Validation échouée: ' . implode(', ', $validation['issues']));
                    }

                    $documentType->setUpdatedAt(new \DateTimeImmutable());

                    $results['success']++;
                    $results['processed'][] = [
                        'type_id' => $documentType->getId(),
                        'code' => $documentType->getCode(),
                        'status' => 'success',
                    ];

                    $this->logger->debug('Document type successfully updated in bulk operation', [
                        'type_id' => $documentType->getId(),
                        'code' => $documentType->getCode(),
                    ]);
                } catch (Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'type_id' => $documentType->getId(),
                        'code' => $documentType->getCode(),
                        'error' => $e->getMessage(),
                    ];
                    
                    $results['processed'][] = [
                        'type_id' => $documentType->getId(),
                        'code' => $documentType->getCode(),
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ];

                    $this->logger->error('Error updating document type in bulk operation', [
                        'type_id' => $documentType->getId(),
                        'code' => $documentType->getCode(),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            // Commit transaction if any succeeded
            if ($results['success'] > 0) {
                $this->entityManager->flush();
                $this->entityManager->commit();

                $this->logger->info('Bulk update transaction committed', [
                    'success_count' => $results['success'],
                    'failed_count' => $results['failed'],
                ]);
            } else {
                $this->entityManager->rollback();

                $this->logger->warning('Bulk update transaction rolled back - no successful updates', [
                    'failed_count' => $results['failed'],
                ]);
            }

            $this->logger->info('Bulk update of document types completed', [
                'total_processed' => count($documentTypes),
                'success_count' => $results['success'],
                'failed_count' => $results['failed'],
                'error_count' => count($results['errors']),
            ]);

            return $results;
        } catch (Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Critical error during bulk update of document types', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'processed_before_error' => $results['success'] + $results['failed'],
            ]);

            return [
                'success' => 0,
                'failed' => count($documentTypes),
                'errors' => [['error' => 'Erreur critique: ' . $e->getMessage()]],
                'processed' => [],
            ];
        }
    }

    /**
     * Archive a document type with all its documents.
     */
    public function archiveDocumentType(DocumentType $documentType, bool $archiveDocuments = true): array
    {
        $this->logger->info('Starting document type archival process', [
            'type_id' => $documentType->getId(),
            'code' => $documentType->getCode(),
            'name' => $documentType->getName(),
            'archive_documents' => $archiveDocuments,
        ]);

        try {
            $this->entityManager->beginTransaction();

            // Check current status
            if (!$documentType->isActive()) {
                $this->logger->warning('Attempting to archive already inactive document type', [
                    'type_id' => $documentType->getId(),
                    'code' => $documentType->getCode(),
                ]);
            }

            $archivedDocuments = 0;
            $failedDocuments = 0;

            // Archive associated documents if requested
            if ($archiveDocuments) {
                $this->logger->debug('Starting archival of associated documents', [
                    'type_id' => $documentType->getId(),
                    'document_count' => $documentType->getDocuments()->count(),
                ]);

                foreach ($documentType->getDocuments() as $document) {
                    try {
                        if ($document->getStatus() !== 'archived') {
                            $oldStatus = $document->getStatus();
                            $document->setStatus('archived');
                            $document->setUpdatedAt(new \DateTimeImmutable());

                            $this->logger->debug('Document archived during type archival', [
                                'document_id' => $document->getId(),
                                'old_status' => $oldStatus,
                                'type_id' => $documentType->getId(),
                            ]);

                            $archivedDocuments++;
                        }
                    } catch (Exception $e) {
                        $failedDocuments++;
                        $this->logger->error('Failed to archive document during type archival', [
                            'document_id' => $document->getId(),
                            'type_id' => $documentType->getId(),
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $this->logger->info('Document archival completed', [
                    'type_id' => $documentType->getId(),
                    'archived_count' => $archivedDocuments,
                    'failed_count' => $failedDocuments,
                ]);
            }

            // Deactivate the document type
            $documentType->setIsActive(false);
            $documentType->setUpdatedAt(new \DateTimeImmutable());

            // Add archival metadata to configuration
            $config = $documentType->getConfiguration() ?? [];
            $config['archived_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $config['archived_documents_count'] = $archivedDocuments;
            $documentType->setConfiguration($config);

            $this->logger->debug('Saving document type archival changes', [
                'type_id' => $documentType->getId(),
                'archived_documents' => $archivedDocuments,
            ]);

            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('Document type successfully archived', [
                'type_id' => $documentType->getId(),
                'code' => $documentType->getCode(),
                'name' => $documentType->getName(),
                'archived_documents' => $archivedDocuments,
                'failed_documents' => $failedDocuments,
            ]);

            return [
                'success' => true,
                'archived_documents' => $archivedDocuments,
                'failed_documents' => $failedDocuments,
                'message' => "Type de document archivé avec succès. {$archivedDocuments} document(s) archivé(s).",
            ];
        } catch (Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Error during document type archival', [
                'error' => $e->getMessage(),
                'type_id' => $documentType->getId() ?? 'null',
                'code' => $documentType->getCode() ?? 'null',
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return [
                'success' => false,
                'error' => 'Erreur lors de l\'archivage du type de document: ' . $e->getMessage(),
            ];
        }
    }
}
