<?php

namespace App\DataFixtures;

use App\Entity\Document\DocumentType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Document Type Fixtures - Creates various document types for testing
 */
class DocumentTypeFixtures extends Fixture
{
    public const LEGAL_TYPE_REFERENCE = 'document-type-legal';
    public const POLICY_TYPE_REFERENCE = 'document-type-policy';
    public const PROCEDURE_TYPE_REFERENCE = 'document-type-procedure';
    public const HANDBOOK_TYPE_REFERENCE = 'document-type-handbook';
    public const TERMS_TYPE_REFERENCE = 'document-type-terms';
    public const ACCESSIBILITY_TYPE_REFERENCE = 'document-type-accessibility';
    public const QUALITY_TYPE_REFERENCE = 'document-type-quality';
    public const TRAINING_TYPE_REFERENCE = 'document-type-training';

    public function load(ObjectManager $manager): void
    {
        $documentTypes = [
            [
                'code' => 'legal_document',
                'name' => 'Document légal',
                'description' => 'Documents légaux et réglementaires nécessaires pour la conformité',
                'icon' => 'fas fa-gavel',
                'color' => '#dc3545',
                'requiresApproval' => true,
                'allowMultiplePublished' => false,
                'hasExpiration' => true,
                'generatesPdf' => true,
                'allowedStatuses' => ['draft', 'review', 'approved', 'published', 'archived'],
                'requiredMetadata' => ['legal_reference', 'effective_date', 'review_date'],
                'configuration' => [
                    'approval_workflow' => true,
                    'version_control' => true,
                    'automatic_archiving' => true,
                    'notification_recipients' => ['legal@eprofos.fr', 'direction@eprofos.fr']
                ],
                'reference' => self::LEGAL_TYPE_REFERENCE,
                'sortOrder' => 1
            ],
            [
                'code' => 'internal_policy',
                'name' => 'Politique interne',
                'description' => 'Politiques internes de l\'organisation et procédures opérationnelles',
                'icon' => 'fas fa-clipboard-list',
                'color' => '#6f42c1',
                'requiresApproval' => true,
                'allowMultiplePublished' => true,
                'hasExpiration' => false,
                'generatesPdf' => true,
                'allowedStatuses' => ['draft', 'review', 'approved', 'published', 'archived'],
                'requiredMetadata' => ['department', 'policy_owner', 'review_frequency'],
                'configuration' => [
                    'approval_workflow' => true,
                    'version_control' => true,
                    'department_specific' => true
                ],
                'reference' => self::POLICY_TYPE_REFERENCE,
                'sortOrder' => 2
            ],
            [
                'code' => 'procedure',
                'name' => 'Procédure',
                'description' => 'Procédures détaillées et guides opérationnels',
                'icon' => 'fas fa-list-ol',
                'color' => '#28a745',
                'requiresApproval' => false,
                'allowMultiplePublished' => true,
                'hasExpiration' => false,
                'generatesPdf' => true,
                'allowedStatuses' => ['draft', 'published', 'archived'],
                'requiredMetadata' => ['process_owner', 'complexity_level'],
                'configuration' => [
                    'step_by_step' => true,
                    'multimedia_support' => true,
                    'user_feedback' => true
                ],
                'reference' => self::PROCEDURE_TYPE_REFERENCE,
                'sortOrder' => 3
            ],
            [
                'code' => 'student_handbook',
                'name' => 'Livret d\'accueil étudiant',
                'description' => 'Documents d\'accueil et d\'orientation pour les étudiants',
                'icon' => 'fas fa-graduation-cap',
                'color' => '#007bff',
                'requiresApproval' => true,
                'allowMultiplePublished' => false,
                'hasExpiration' => false,
                'generatesPdf' => true,
                'allowedStatuses' => ['draft', 'review', 'approved', 'published', 'archived'],
                'requiredMetadata' => ['academic_year', 'target_audience', 'language'],
                'configuration' => [
                    'multilingual' => true,
                    'downloadable' => true,
                    'print_friendly' => true
                ],
                'reference' => self::HANDBOOK_TYPE_REFERENCE,
                'sortOrder' => 4
            ],
            [
                'code' => 'terms_conditions',
                'name' => 'Conditions générales',
                'description' => 'Conditions générales de vente et d\'utilisation',
                'icon' => 'fas fa-handshake',
                'color' => '#fd7e14',
                'requiresApproval' => true,
                'allowMultiplePublished' => false,
                'hasExpiration' => false,
                'generatesPdf' => true,
                'allowedStatuses' => ['draft', 'review', 'approved', 'published', 'archived'],
                'requiredMetadata' => ['version_number', 'effective_date', 'legal_review_date'],
                'configuration' => [
                    'legal_review_required' => true,
                    'customer_notification' => true,
                    'archive_previous_versions' => true
                ],
                'reference' => self::TERMS_TYPE_REFERENCE,
                'sortOrder' => 5
            ],
            [
                'code' => 'accessibility_document',
                'name' => 'Document d\'accessibilité',
                'description' => 'Documents relatifs à l\'accessibilité et à l\'inclusion',
                'icon' => 'fas fa-universal-access',
                'color' => '#20c997',
                'requiresApproval' => true,
                'allowMultiplePublished' => true,
                'hasExpiration' => false,
                'generatesPdf' => true,
                'allowedStatuses' => ['draft', 'review', 'approved', 'published', 'archived'],
                'requiredMetadata' => ['accessibility_level', 'compliance_standards', 'review_date'],
                'configuration' => [
                    'accessibility_compliant' => true,
                    'screen_reader_friendly' => true,
                    'high_contrast' => true,
                    'multiple_formats' => true
                ],
                'reference' => self::ACCESSIBILITY_TYPE_REFERENCE,
                'sortOrder' => 6
            ],
            [
                'code' => 'quality_document',
                'name' => 'Document qualité',
                'description' => 'Documents liés au système qualité et à la certification Qualiopi',
                'icon' => 'fas fa-certificate',
                'color' => '#ffc107',
                'requiresApproval' => true,
                'allowMultiplePublished' => true,
                'hasExpiration' => true,
                'generatesPdf' => true,
                'allowedStatuses' => ['draft', 'review', 'approved', 'published', 'archived', 'expired'],
                'requiredMetadata' => ['qualiopi_criterion', 'audit_date', 'responsible_person'],
                'configuration' => [
                    'qualiopi_compliance' => true,
                    'audit_trail' => true,
                    'continuous_improvement' => true,
                    'performance_indicators' => true
                ],
                'reference' => self::QUALITY_TYPE_REFERENCE,
                'sortOrder' => 7
            ],
            [
                'code' => 'training_material',
                'name' => 'Support de formation',
                'description' => 'Supports pédagogiques et matériel de formation',
                'icon' => 'fas fa-chalkboard-teacher',
                'color' => '#e83e8c',
                'requiresApproval' => false,
                'allowMultiplePublished' => true,
                'hasExpiration' => false,
                'generatesPdf' => false,
                'allowedStatuses' => ['draft', 'published', 'archived'],
                'requiredMetadata' => ['skill_level', 'duration', 'target_audience'],
                'configuration' => [
                    'interactive_content' => true,
                    'multimedia_support' => true,
                    'progress_tracking' => true,
                    'downloadable_resources' => true
                ],
                'reference' => self::TRAINING_TYPE_REFERENCE,
                'sortOrder' => 8
            ]
        ];

        foreach ($documentTypes as $typeData) {
            $documentType = new DocumentType();
            $documentType->setCode($typeData['code'])
                        ->setName($typeData['name'])
                        ->setDescription($typeData['description'])
                        ->setIcon($typeData['icon'])
                        ->setColor($typeData['color'])
                        ->setRequiresApproval($typeData['requiresApproval'])
                        ->setAllowMultiplePublished($typeData['allowMultiplePublished'])
                        ->setHasExpiration($typeData['hasExpiration'])
                        ->setGeneratesPdf($typeData['generatesPdf'])
                        ->setAllowedStatuses($typeData['allowedStatuses'])
                        ->setRequiredMetadata($typeData['requiredMetadata'])
                        ->setConfiguration($typeData['configuration'])
                        ->setSortOrder($typeData['sortOrder'])
                        ->setIsActive(true);

            $manager->persist($documentType);
            $this->addReference($typeData['reference'], $documentType);
        }

        $manager->flush();
    }
}
