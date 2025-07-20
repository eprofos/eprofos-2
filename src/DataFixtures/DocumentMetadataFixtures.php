<?php

namespace App\DataFixtures;

use App\Entity\Document\DocumentMetadata;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Document Metadata Fixtures - Creates structured metadata for documents
 * 
 * Provides comprehensive metadata examples for various document types,
 * demonstrating the flexible metadata system capabilities.
 */
class DocumentMetadataFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Metadata for CGVF Document
        $cgvfMetadata = [
            [
                'document' => DocumentFixtures::CGVF_DOCUMENT_REFERENCE,
                'metaKey' => 'legal_reference',
                'metaValue' => 'Code du travail - Articles L. 6353-1 et suivants',
                'dataType' => DocumentMetadata::TYPE_STRING,
                'displayName' => 'Référence légale',
                'description' => 'Base légale du document',
                'isRequired' => true,
                'isSearchable' => true,
                'sortOrder' => 1
            ],
            [
                'document' => DocumentFixtures::CGVF_DOCUMENT_REFERENCE,
                'metaKey' => 'effective_date',
                'metaValue' => '2025-01-01',
                'dataType' => DocumentMetadata::TYPE_DATE,
                'displayName' => 'Date d\'entrée en vigueur',
                'description' => 'Date d\'application du document',
                'isRequired' => true,
                'isSearchable' => true,
                'sortOrder' => 2
            ],
            [
                'document' => DocumentFixtures::CGVF_DOCUMENT_REFERENCE,
                'metaKey' => 'review_date',
                'metaValue' => '2026-01-01',
                'dataType' => DocumentMetadata::TYPE_DATE,
                'displayName' => 'Date de révision prévue',
                'description' => 'Prochaine date de révision du document',
                'isRequired' => false,
                'isSearchable' => true,
                'sortOrder' => 3
            ],
            [
                'document' => DocumentFixtures::CGVF_DOCUMENT_REFERENCE,
                'metaKey' => 'approval_authority',
                'metaValue' => 'Direction Générale EPROFOS',
                'dataType' => DocumentMetadata::TYPE_STRING,
                'displayName' => 'Autorité d\'approbation',
                'description' => 'Qui a approuvé ce document',
                'isRequired' => true,
                'isSearchable' => false,
                'sortOrder' => 4
            ]
        ];

        // Metadata for Accessibility Policy
        $accessibilityMetadata = [
            [
                'document' => DocumentFixtures::ACCESSIBILITY_POLICY_REFERENCE,
                'metaKey' => 'accessibility_level',
                'metaValue' => 'AA',
                'dataType' => DocumentMetadata::TYPE_STRING,
                'displayName' => 'Niveau d\'accessibilité',
                'description' => 'Niveau WCAG visé',
                'isRequired' => true,
                'isSearchable' => true,
                'validationRules' => [
                    'choices' => ['A', 'AA', 'AAA']
                ],
                'sortOrder' => 1
            ],
            [
                'document' => DocumentFixtures::ACCESSIBILITY_POLICY_REFERENCE,
                'metaKey' => 'compliance_standards',
                'metaValue' => 'RGAA 4.1, WCAG 2.1',
                'dataType' => DocumentMetadata::TYPE_STRING,
                'displayName' => 'Standards de conformité',
                'description' => 'Standards d\'accessibilité appliqués',
                'isRequired' => true,
                'isSearchable' => true,
                'sortOrder' => 2
            ],
            [
                'document' => DocumentFixtures::ACCESSIBILITY_POLICY_REFERENCE,
                'metaKey' => 'audit_date',
                'metaValue' => '2024-12-15',
                'dataType' => DocumentMetadata::TYPE_DATE,
                'displayName' => 'Date du dernier audit',
                'description' => 'Dernière évaluation d\'accessibilité',
                'isRequired' => false,
                'isSearchable' => true,
                'sortOrder' => 3
            ],
            [
                'document' => DocumentFixtures::ACCESSIBILITY_POLICY_REFERENCE,
                'metaKey' => 'next_review',
                'metaValue' => '2025-12-15',
                'dataType' => DocumentMetadata::TYPE_DATE,
                'displayName' => 'Prochaine révision',
                'description' => 'Date de la prochaine révision prévue',
                'isRequired' => false,
                'isSearchable' => true,
                'sortOrder' => 4
            ]
        ];

        // Metadata for Quality Manual
        $qualityMetadata = [
            [
                'document' => DocumentFixtures::QUALITY_MANUAL_REFERENCE,
                'metaKey' => 'qualiopi_criterion',
                'metaValue' => 'Critères 1-7 Qualiopi',
                'dataType' => DocumentMetadata::TYPE_STRING,
                'displayName' => 'Critères Qualiopi couverts',
                'description' => 'Critères de certification Qualiopi',
                'isRequired' => true,
                'isSearchable' => true,
                'sortOrder' => 1
            ],
            [
                'document' => DocumentFixtures::QUALITY_MANUAL_REFERENCE,
                'metaKey' => 'certification_body',
                'metaValue' => 'AFNOR Certification',
                'dataType' => DocumentMetadata::TYPE_STRING,
                'displayName' => 'Organisme certificateur',
                'description' => 'Organisme ayant délivré la certification',
                'isRequired' => true,
                'isSearchable' => false,
                'sortOrder' => 2
            ],
            [
                'document' => DocumentFixtures::QUALITY_MANUAL_REFERENCE,
                'metaKey' => 'audit_date',
                'metaValue' => '2024-06-15',
                'dataType' => DocumentMetadata::TYPE_DATE,
                'displayName' => 'Date d\'audit Qualiopi',
                'description' => 'Date du dernier audit de certification',
                'isRequired' => true,
                'isSearchable' => true,
                'sortOrder' => 3
            ],
            [
                'document' => DocumentFixtures::QUALITY_MANUAL_REFERENCE,
                'metaKey' => 'responsible_person',
                'metaValue' => 'Marie DUBOIS - Responsable Qualité',
                'dataType' => DocumentMetadata::TYPE_STRING,
                'displayName' => 'Responsable qualité',
                'description' => 'Personne responsable du système qualité',
                'isRequired' => true,
                'isSearchable' => false,
                'sortOrder' => 4
            ],
            [
                'document' => DocumentFixtures::QUALITY_MANUAL_REFERENCE,
                'metaKey' => 'kpi_indicators',
                'metaValue' => '{"satisfaction_rate": 94, "success_rate": 87, "employment_rate": 79}',
                'dataType' => DocumentMetadata::TYPE_JSON,
                'displayName' => 'Indicateurs de performance',
                'description' => 'KPI du système qualité',
                'isRequired' => false,
                'isSearchable' => false,
                'sortOrder' => 5
            ]
        ];

        // Metadata for Student Handbook
        $handbookMetadata = [
            [
                'document' => DocumentFixtures::STUDENT_HANDBOOK_REFERENCE,
                'metaKey' => 'academic_year',
                'metaValue' => '2025',
                'dataType' => DocumentMetadata::TYPE_STRING,
                'displayName' => 'Année académique',
                'description' => 'Année scolaire de référence',
                'isRequired' => true,
                'isSearchable' => true,
                'sortOrder' => 1
            ],
            [
                'document' => DocumentFixtures::STUDENT_HANDBOOK_REFERENCE,
                'metaKey' => 'target_audience',
                'metaValue' => 'Nouveaux apprenants',
                'dataType' => DocumentMetadata::TYPE_STRING,
                'displayName' => 'Public cible',
                'description' => 'Destinataires du document',
                'isRequired' => true,
                'isSearchable' => true,
                'sortOrder' => 2
            ],
            [
                'document' => DocumentFixtures::STUDENT_HANDBOOK_REFERENCE,
                'metaKey' => 'language',
                'metaValue' => 'Français',
                'dataType' => DocumentMetadata::TYPE_STRING,
                'displayName' => 'Langue',
                'description' => 'Langue du document',
                'isRequired' => true,
                'isSearchable' => true,
                'validationRules' => [
                    'choices' => ['Français', 'English', 'Español']
                ],
                'sortOrder' => 3
            ],
            [
                'document' => DocumentFixtures::STUDENT_HANDBOOK_REFERENCE,
                'metaKey' => 'digital_version_url',
                'metaValue' => 'https://eprofos.fr/documents/livret-accueil-2025.pdf',
                'dataType' => DocumentMetadata::TYPE_URL,
                'displayName' => 'Version numérique',
                'description' => 'URL de téléchargement',
                'isRequired' => false,
                'isSearchable' => false,
                'validationRules' => [
                    'url' => true
                ],
                'sortOrder' => 4
            ]
        ];

        // Metadata for Privacy Policy
        $privacyMetadata = [
            [
                'document' => DocumentFixtures::PRIVACY_POLICY_REFERENCE,
                'metaKey' => 'gdpr_compliance',
                'metaValue' => 'true',
                'dataType' => DocumentMetadata::TYPE_BOOLEAN,
                'displayName' => 'Conformité RGPD',
                'description' => 'Document conforme au RGPD',
                'isRequired' => true,
                'isSearchable' => true,
                'sortOrder' => 1
            ],
            [
                'document' => DocumentFixtures::PRIVACY_POLICY_REFERENCE,
                'metaKey' => 'dpo_contact',
                'metaValue' => 'dpo@eprofos.fr',
                'dataType' => DocumentMetadata::TYPE_STRING,
                'displayName' => 'Contact DPO',
                'description' => 'Email du délégué à la protection des données',
                'isRequired' => true,
                'isSearchable' => false,
                'validationRules' => [
                    'email' => true
                ],
                'sortOrder' => 2
            ],
            [
                'document' => DocumentFixtures::PRIVACY_POLICY_REFERENCE,
                'metaKey' => 'data_retention_period',
                'metaValue' => '3',
                'dataType' => DocumentMetadata::TYPE_INTEGER,
                'displayName' => 'Durée de conservation (années)',
                'description' => 'Durée de conservation des données personnelles',
                'isRequired' => true,
                'isSearchable' => true,
                'validationRules' => [
                    'min' => 1,
                    'max' => 10
                ],
                'sortOrder' => 3
            ]
        ];

        // Combine all metadata
        $allMetadata = array_merge(
            $cgvfMetadata,
            $accessibilityMetadata,
            $qualityMetadata,
            $handbookMetadata,
            $privacyMetadata
        );

        foreach ($allMetadata as $metaData) {
            $metadata = new DocumentMetadata();
            $metadata->setDocument($this->getReference($metaData['document'], \App\Entity\Document\Document::class))
                    ->setMetaKey($metaData['metaKey'])
                    ->setMetaValue($metaData['metaValue'])
                    ->setDataType($metaData['dataType'])
                    ->setDisplayName($metaData['displayName'] ?? null)
                    ->setDescription($metaData['description'] ?? null)
                    ->setIsRequired($metaData['isRequired'] ?? false)
                    ->setIsSearchable($metaData['isSearchable'] ?? true)
                    ->setIsEditable(true)
                    ->setSortOrder($metaData['sortOrder'] ?? 0);

            if (isset($metaData['validationRules'])) {
                $metadata->setValidationRules($metaData['validationRules']);
            }

            $manager->persist($metadata);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            DocumentFixtures::class,
        ];
    }
}
