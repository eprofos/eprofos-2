<?php

declare(strict_types=1);

namespace App\Controller\Admin\Document;

use App\Entity\Document\DocumentType;
use App\Form\Document\DocumentTypeType;
use App\Repository\Document\DocumentTypeRepository;
use App\Service\Document\DocumentTypeService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin Document Type Controller.
 *
 * Handles CRUD operations for document types in the admin interface.
 * Provides management for the flexible document type system.
 */
#[Route('/admin/document-types')]
#[IsGranted('ROLE_ADMIN')]
class DocumentTypeController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger,
        private DocumentTypeService $documentTypeService,
    ) {}

    /**
     * List all document types with statistics.
     */
    #[Route('/', name: 'admin_document_type_index', methods: ['GET'])]
    public function index(DocumentTypeRepository $documentTypeRepository): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        
        $this->logger->info('Admin document types list accessed', [
            'user' => $userId,
            'timestamp' => new \DateTimeImmutable(),
            'action' => 'index',
            'controller' => 'DocumentTypeController',
        ]);

        try {
            $this->logger->debug('Fetching document types with statistics', [
                'user' => $userId,
                'service_method' => 'getDocumentTypesWithStats',
            ]);

            // Get document types with statistics
            $typesWithStats = $this->documentTypeService->getDocumentTypesWithStats();

            $this->logger->info('Document types statistics successfully retrieved', [
                'user' => $userId,
                'types_count' => count($typesWithStats),
                'statistics_included' => true,
            ]);

            return $this->render('admin/document_type/index.html.twig', [
                'types_with_stats' => $typesWithStats,
                'page_title' => 'Types de documents',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Types de documents', 'url' => null],
                ],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error retrieving document types list', [
                'user' => $userId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des types de documents.');
            
            return $this->render('admin/document_type/index.html.twig', [
                'types_with_stats' => [],
                'page_title' => 'Types de documents',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Types de documents', 'url' => null],
                ],
            ]);
        }
    }

    /**
     * Show document type details.
     */
    #[Route('/{id}', name: 'admin_document_type_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(DocumentType $documentType): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $typeId = $documentType->getId();
        
        $this->logger->info('Admin document type details viewed', [
            'type_id' => $typeId,
            'type_name' => $documentType->getName(),
            'type_code' => $documentType->getCode(),
            'user' => $userId,
            'timestamp' => new \DateTimeImmutable(),
            'action' => 'show',
        ]);

        try {
            $this->logger->debug('Computing document type statistics', [
                'type_id' => $typeId,
                'user' => $userId,
                'documents_collection_count' => $documentType->getDocuments()->count(),
                'templates_collection_count' => $documentType->getTemplates()->count(),
            ]);

            // Get type statistics
            $stats = [
                'document_count' => $documentType->getDocuments()->count(),
                'template_count' => $documentType->getTemplates()->count(),
                'published_count' => $documentType->getDocuments()->filter(static fn ($doc) => $doc->getStatus() === 'published')->count(),
                'draft_count' => $documentType->getDocuments()->filter(static fn ($doc) => $doc->getStatus() === 'draft')->count(),
            ];

            $this->logger->info('Document type statistics computed successfully', [
                'type_id' => $typeId,
                'user' => $userId,
                'stats' => $stats,
            ]);

            return $this->render('admin/document_type/show.html.twig', [
                'document_type' => $documentType,
                'stats' => $stats,
                'page_title' => 'Type: ' . $documentType->getName(),
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Types de documents', 'url' => $this->generateUrl('admin_document_type_index')],
                    ['label' => $documentType->getName(), 'url' => null],
                ],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error displaying document type details', [
                'type_id' => $typeId,
                'user' => $userId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du chargement des détails du type de document.');
            
            return $this->redirectToRoute('admin_document_type_index');
        }
    }

    /**
     * Create a new document type.
     */
    #[Route('/new', name: 'admin_document_type_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        
        $this->logger->info('Starting new document type creation', [
            'user' => $userId,
            'timestamp' => new \DateTimeImmutable(),
            'action' => 'new',
            'method' => $request->getMethod(),
        ]);

        try {
            $documentType = new DocumentType();

            $this->logger->debug('Setting default values for new document type', [
                'user' => $userId,
                'defaults' => [
                    'is_active' => true,
                    'allow_multiple_published' => true,
                ],
            ]);

            // Set default values
            $documentType->setIsActive(true);
            $documentType->setAllowMultiplePublished(true);
            $documentType->setSortOrder($this->documentTypeService->getNextSortOrder());

            $form = $this->createForm(DocumentTypeType::class, $documentType);
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Document type form submitted', [
                    'user' => $userId,
                    'form_valid' => $form->isValid(),
                    'form_errors' => $form->isValid() ? [] : (string) $form->getErrors(true),
                    'submitted_data' => [
                        'name' => $documentType->getName(),
                        'code' => $documentType->getCode(),
                        'is_active' => $documentType->isActive(),
                    ],
                ]);

                if ($form->isValid()) {
                    $this->logger->debug('Calling document type service to create new type', [
                        'user' => $userId,
                        'type_data' => [
                            'name' => $documentType->getName(),
                            'code' => $documentType->getCode(),
                            'description' => $documentType->getDescription(),
                            'sort_order' => $documentType->getSortOrder(),
                        ],
                    ]);

                    $result = $this->documentTypeService->createDocumentType($documentType);

                    if ($result['success']) {
                        $this->logger->info('Document type created successfully', [
                            'user' => $userId,
                            'type_id' => $documentType->getId(),
                            'type_name' => $documentType->getName(),
                            'type_code' => $documentType->getCode(),
                        ]);

                        $this->addFlash('success', 'Le type de document a été créé avec succès.');

                        return $this->redirectToRoute('admin_document_type_show', ['id' => $documentType->getId()]);
                    }
                    
                    $this->logger->warning('Document type creation failed via service', [
                        'user' => $userId,
                        'error' => $result['error'],
                        'type_data' => [
                            'name' => $documentType->getName(),
                            'code' => $documentType->getCode(),
                        ],
                    ]);
                    
                    $this->addFlash('error', $result['error']);
                }
            }

            return $this->render('admin/document_type/new.html.twig', [
                'document_type' => $documentType,
                'form' => $form,
                'page_title' => 'Nouveau type de document',
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Types de documents', 'url' => $this->generateUrl('admin_document_type_index')],
                    ['label' => 'Nouveau', 'url' => null],
                ],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error in document type creation process', [
                'user' => $userId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'request_method' => $request->getMethod(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la création du type de document.');
            
            return $this->redirectToRoute('admin_document_type_index');
        }
    }

    /**
     * Edit an existing document type.
     */
    #[Route('/{id}/edit', name: 'admin_document_type_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, DocumentType $documentType): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $typeId = $documentType->getId();
        
        $this->logger->info('Starting document type edit', [
            'type_id' => $typeId,
            'type_name' => $documentType->getName(),
            'type_code' => $documentType->getCode(),
            'user' => $userId,
            'timestamp' => new \DateTimeImmutable(),
            'action' => 'edit',
            'method' => $request->getMethod(),
        ]);

        try {
            $originalData = [
                'name' => $documentType->getName(),
                'code' => $documentType->getCode(),
                'description' => $documentType->getDescription(),
                'is_active' => $documentType->isActive(),
            ];

            $this->logger->debug('Original document type data captured', [
                'type_id' => $typeId,
                'user' => $userId,
                'original_data' => $originalData,
            ]);

            $form = $this->createForm(DocumentTypeType::class, $documentType);
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                $this->logger->info('Document type edit form submitted', [
                    'type_id' => $typeId,
                    'user' => $userId,
                    'form_valid' => $form->isValid(),
                    'form_errors' => $form->isValid() ? [] : (string) $form->getErrors(true),
                    'updated_data' => [
                        'name' => $documentType->getName(),
                        'code' => $documentType->getCode(),
                        'description' => $documentType->getDescription(),
                        'is_active' => $documentType->isActive(),
                    ],
                ]);

                if ($form->isValid()) {
                    $this->logger->debug('Calling document type service to update type', [
                        'type_id' => $typeId,
                        'user' => $userId,
                        'changes_detected' => $originalData !== [
                            'name' => $documentType->getName(),
                            'code' => $documentType->getCode(),
                            'description' => $documentType->getDescription(),
                            'is_active' => $documentType->isActive(),
                        ],
                    ]);

                    $result = $this->documentTypeService->updateDocumentType($documentType);

                    if ($result['success']) {
                        $this->logger->info('Document type updated successfully', [
                            'type_id' => $typeId,
                            'user' => $userId,
                            'type_name' => $documentType->getName(),
                            'original_data' => $originalData,
                            'updated_data' => [
                                'name' => $documentType->getName(),
                                'code' => $documentType->getCode(),
                                'description' => $documentType->getDescription(),
                                'is_active' => $documentType->isActive(),
                            ],
                        ]);

                        $this->addFlash('success', 'Le type de document a été modifié avec succès.');

                        return $this->redirectToRoute('admin_document_type_show', ['id' => $documentType->getId()]);
                    }
                    
                    $this->logger->warning('Document type update failed via service', [
                        'type_id' => $typeId,
                        'user' => $userId,
                        'error' => $result['error'],
                    ]);
                    
                    $this->addFlash('error', $result['error']);
                }
            }

            return $this->render('admin/document_type/edit.html.twig', [
                'document_type' => $documentType,
                'form' => $form,
                'page_title' => 'Modifier: ' . $documentType->getName(),
                'breadcrumb' => [
                    ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard')],
                    ['label' => 'Types de documents', 'url' => $this->generateUrl('admin_document_type_index')],
                    ['label' => $documentType->getName(), 'url' => $this->generateUrl('admin_document_type_show', ['id' => $documentType->getId()])],
                    ['label' => 'Modifier', 'url' => null],
                ],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error in document type edit process', [
                'type_id' => $typeId,
                'user' => $userId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'request_method' => $request->getMethod(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la modification du type de document.');
            
            return $this->redirectToRoute('admin_document_type_show', ['id' => $typeId]);
        }
    }

    /**
     * Delete a document type.
     */
    #[Route('/{id}', name: 'admin_document_type_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, DocumentType $documentType): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $typeId = $documentType->getId();
        $typeName = $documentType->getName();
        $typeCode = $documentType->getCode();
        
        $this->logger->info('Document type deletion attempt', [
            'type_id' => $typeId,
            'type_name' => $typeName,
            'type_code' => $typeCode,
            'user' => $userId,
            'timestamp' => new \DateTimeImmutable(),
            'action' => 'delete',
        ]);

        try {
            $token = $request->getPayload()->get('_token');
            $expectedToken = 'delete' . $typeId;
            
            $this->logger->debug('CSRF token validation for deletion', [
                'type_id' => $typeId,
                'user' => $userId,
                'token_provided' => !empty($token),
                'expected_token_prefix' => 'delete' . $typeId,
            ]);

            if ($this->isCsrfTokenValid($expectedToken, $token)) {
                $this->logger->debug('CSRF token valid, proceeding with deletion', [
                    'type_id' => $typeId,
                    'user' => $userId,
                    'type_stats' => [
                        'document_count' => $documentType->getDocuments()->count(),
                        'template_count' => $documentType->getTemplates()->count(),
                    ],
                ]);

                $result = $this->documentTypeService->deleteDocumentType($documentType);

                if ($result['success']) {
                    $this->logger->info('Document type deleted successfully', [
                        'type_id' => $typeId,
                        'type_name' => $typeName,
                        'type_code' => $typeCode,
                        'user' => $userId,
                    ]);
                    
                    $this->addFlash('success', 'Le type de document a été supprimé avec succès.');
                } else {
                    $this->logger->warning('Document type deletion failed via service', [
                        'type_id' => $typeId,
                        'type_name' => $typeName,
                        'user' => $userId,
                        'error' => $result['error'],
                    ]);
                    
                    $this->addFlash('error', $result['error']);
                }
            } else {
                $this->logger->warning('Invalid CSRF token for document type deletion', [
                    'type_id' => $typeId,
                    'user' => $userId,
                    'token_provided' => !empty($token),
                ]);
                
                $this->addFlash('error', 'Token de sécurité invalide.');
            }
        } catch (\Exception $e) {
            $this->logger->error('Error during document type deletion', [
                'type_id' => $typeId,
                'type_name' => $typeName,
                'user' => $userId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la suppression du type de document.');
        }

        return $this->redirectToRoute('admin_document_type_index');
    }

    /**
     * Toggle document type active status.
     */
    #[Route('/{id}/toggle-status', name: 'admin_document_type_toggle_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleStatus(Request $request, DocumentType $documentType): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $typeId = $documentType->getId();
        $typeName = $documentType->getName();
        $currentStatus = $documentType->isActive();
        
        $this->logger->info('Document type status toggle attempt', [
            'type_id' => $typeId,
            'type_name' => $typeName,
            'current_status' => $currentStatus,
            'target_status' => !$currentStatus,
            'user' => $userId,
            'timestamp' => new \DateTimeImmutable(),
            'action' => 'toggle_status',
        ]);

        try {
            $token = $request->getPayload()->get('_token');
            $expectedToken = 'toggle' . $typeId;
            
            $this->logger->debug('CSRF token validation for status toggle', [
                'type_id' => $typeId,
                'user' => $userId,
                'token_provided' => !empty($token),
                'expected_token_prefix' => 'toggle' . $typeId,
            ]);

            if ($this->isCsrfTokenValid($expectedToken, $token)) {
                $this->logger->debug('CSRF token valid, proceeding with status toggle', [
                    'type_id' => $typeId,
                    'user' => $userId,
                    'current_status' => $currentStatus,
                ]);

                $result = $this->documentTypeService->toggleActiveStatus($documentType);

                if ($result['success']) {
                    $newStatus = $documentType->isActive();
                    
                    $this->logger->info('Document type status toggled successfully', [
                        'type_id' => $typeId,
                        'type_name' => $typeName,
                        'previous_status' => $currentStatus,
                        'new_status' => $newStatus,
                        'user' => $userId,
                        'result_message' => $result['message'],
                    ]);
                    
                    $this->addFlash('success', $result['message']);
                } else {
                    $this->logger->warning('Document type status toggle failed via service', [
                        'type_id' => $typeId,
                        'type_name' => $typeName,
                        'user' => $userId,
                        'error' => $result['error'],
                    ]);
                    
                    $this->addFlash('error', $result['error']);
                }
            } else {
                $this->logger->warning('Invalid CSRF token for document type status toggle', [
                    'type_id' => $typeId,
                    'user' => $userId,
                    'token_provided' => !empty($token),
                ]);
                
                $this->addFlash('error', 'Token de sécurité invalide.');
            }
        } catch (\Exception $e) {
            $this->logger->error('Error during document type status toggle', [
                'type_id' => $typeId,
                'type_name' => $typeName,
                'user' => $userId,
                'current_status' => $currentStatus,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors du changement de statut du type de document.');
        }

        return $this->redirectToRoute('admin_document_type_index');
    }

    /**
     * Duplicate a document type.
     */
    #[Route('/{id}/duplicate', name: 'admin_document_type_duplicate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function duplicate(Request $request, DocumentType $documentType): Response
    {
        $userId = $this->getUser()?->getUserIdentifier();
        $sourceTypeId = $documentType->getId();
        $sourceTypeName = $documentType->getName();
        $sourceTypeCode = $documentType->getCode();
        
        $this->logger->info('Document type duplication attempt', [
            'source_type_id' => $sourceTypeId,
            'source_type_name' => $sourceTypeName,
            'source_type_code' => $sourceTypeCode,
            'user' => $userId,
            'timestamp' => new \DateTimeImmutable(),
            'action' => 'duplicate',
        ]);

        try {
            $token = $request->getPayload()->get('_token');
            $expectedToken = 'duplicate' . $sourceTypeId;
            
            $this->logger->debug('CSRF token validation for duplication', [
                'source_type_id' => $sourceTypeId,
                'user' => $userId,
                'token_provided' => !empty($token),
                'expected_token_prefix' => 'duplicate' . $sourceTypeId,
            ]);

            if ($this->isCsrfTokenValid($expectedToken, $token)) {
                $this->logger->debug('CSRF token valid, creating document type copy', [
                    'source_type_id' => $sourceTypeId,
                    'user' => $userId,
                    'source_properties' => [
                        'name' => $sourceTypeName,
                        'code' => $sourceTypeCode,
                        'description' => $documentType->getDescription(),
                        'requires_approval' => $documentType->isRequiresApproval(),
                        'allow_multiple_published' => $documentType->isAllowMultiplePublished(),
                        'has_expiration' => $documentType->isHasExpiration(),
                        'generates_pdf' => $documentType->isGeneratesPdf(),
                    ],
                ]);

                // Create a copy of the document type
                $newDocumentType = new DocumentType();
                $newCode = $sourceTypeCode . '_copy';
                $newName = $sourceTypeName . ' (Copie)';
                
                $newDocumentType->setCode($newCode)
                    ->setName($newName)
                    ->setDescription($documentType->getDescription())
                    ->setIcon($documentType->getIcon())
                    ->setColor($documentType->getColor())
                    ->setRequiresApproval($documentType->isRequiresApproval())
                    ->setAllowMultiplePublished($documentType->isAllowMultiplePublished())
                    ->setHasExpiration($documentType->isHasExpiration())
                    ->setGeneratesPdf($documentType->isGeneratesPdf())
                    ->setAllowedStatuses($documentType->getAllowedStatuses())
                    ->setRequiredMetadata($documentType->getRequiredMetadata())
                    ->setConfiguration($documentType->getConfiguration())
                    ->setIsActive(false) // Start as inactive
                    ->setSortOrder($this->documentTypeService->getNextSortOrder())
                ;

                $this->logger->debug('New document type configured', [
                    'source_type_id' => $sourceTypeId,
                    'user' => $userId,
                    'new_type_data' => [
                        'code' => $newCode,
                        'name' => $newName,
                        'is_active' => false,
                        'sort_order' => $newDocumentType->getSortOrder(),
                    ],
                ]);

                $result = $this->documentTypeService->createDocumentType($newDocumentType);

                if ($result['success']) {
                    $newTypeId = $newDocumentType->getId();
                    
                    $this->logger->info('Document type duplicated successfully', [
                        'source_type_id' => $sourceTypeId,
                        'source_type_name' => $sourceTypeName,
                        'new_type_id' => $newTypeId,
                        'new_type_name' => $newName,
                        'new_type_code' => $newCode,
                        'user' => $userId,
                    ]);
                    
                    $this->addFlash('success', 'Le type de document a été dupliqué avec succès.');

                    return $this->redirectToRoute('admin_document_type_edit', ['id' => $newTypeId]);
                }
                
                $this->logger->warning('Document type duplication failed via service', [
                    'source_type_id' => $sourceTypeId,
                    'user' => $userId,
                    'error' => $result['error'],
                    'attempted_name' => $newName,
                    'attempted_code' => $newCode,
                ]);
                
                $this->addFlash('error', $result['error']);
            } else {
                $this->logger->warning('Invalid CSRF token for document type duplication', [
                    'source_type_id' => $sourceTypeId,
                    'user' => $userId,
                    'token_provided' => !empty($token),
                ]);
                
                $this->addFlash('error', 'Token de sécurité invalide.');
            }
        } catch (\Exception $e) {
            $this->logger->error('Error during document type duplication', [
                'source_type_id' => $sourceTypeId,
                'source_type_name' => $sourceTypeName,
                'user' => $userId,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);

            $this->addFlash('error', 'Une erreur est survenue lors de la duplication du type de document.');
        }

        return $this->redirectToRoute('admin_document_type_index');
    }
}
