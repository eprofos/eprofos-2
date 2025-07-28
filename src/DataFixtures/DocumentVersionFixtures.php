<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Document\Document;
use App\Entity\Document\DocumentVersion;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Document Version Fixtures - Creates version history for documents.
 *
 * Provides comprehensive version tracking examples demonstrating
 * the audit trail capabilities essential for Qualiopi compliance
 * and document management best practices.
 */
class DocumentVersionFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Version history for CGVF Document
        $cgvfVersions = [
            [
                'document' => DocumentFixtures::CGVF_DOCUMENT_REFERENCE,
                'version' => '1.0',
                'title' => 'Conditions Générales de Vente de Formation (CGVF)',
                'content' => 'Version initiale des conditions générales...',
                'changeLog' => 'Création initiale du document CGVF conforme à la réglementation en vigueur.',
                'isCurrent' => false,
                'createdAt' => new DateTimeImmutable('-8 months'),
                'fileSize' => 15420,
            ],
            [
                'document' => DocumentFixtures::CGVF_DOCUMENT_REFERENCE,
                'version' => '2.0',
                'title' => 'Conditions Générales de Vente de Formation (CGVF)',
                'content' => 'Version mise à jour avec nouvelles modalités...',
                'changeLog' => 'Mise à jour majeure : ajout des modalités de formation à distance, révision des conditions d\'annulation, intégration des obligations CPF.',
                'isCurrent' => false,
                'createdAt' => new DateTimeImmutable('-5 months'),
                'fileSize' => 18750,
            ],
            [
                'document' => DocumentFixtures::CGVF_DOCUMENT_REFERENCE,
                'version' => '2.1',
                'title' => 'Conditions Générales de Vente de Formation (CGVF)',
                'content' => null, // Current version - content in main document
                'changeLog' => 'Correction mineure : précision sur les délais de rétractation et mise à jour des coordonnées de contact.',
                'isCurrent' => true,
                'createdAt' => new DateTimeImmutable('-3 months'),
                'fileSize' => 19200,
            ],
        ];

        // Version history for Accessibility Policy
        $accessibilityVersions = [
            [
                'document' => DocumentFixtures::ACCESSIBILITY_POLICY_REFERENCE,
                'version' => '1.0',
                'title' => 'Politique d\'Accessibilité Numérique',
                'content' => 'Version initiale de la politique d\'accessibilité...',
                'changeLog' => 'Création de la politique d\'accessibilité conforme RGAA 4.0.',
                'isCurrent' => false,
                'createdAt' => new DateTimeImmutable('-6 months'),
                'fileSize' => 8500,
            ],
            [
                'document' => DocumentFixtures::ACCESSIBILITY_POLICY_REFERENCE,
                'version' => '1.1',
                'title' => 'Politique d\'Accessibilité Numérique',
                'content' => 'Mise à jour avec nouveaux critères...',
                'changeLog' => 'Ajout des critères RGAA 4.1, mise à jour du plan d\'amélioration.',
                'isCurrent' => false,
                'createdAt' => new DateTimeImmutable('-4 months'),
                'fileSize' => 9200,
            ],
            [
                'document' => DocumentFixtures::ACCESSIBILITY_POLICY_REFERENCE,
                'version' => '1.2',
                'title' => 'Politique d\'Accessibilité Numérique',
                'content' => 'Révision suite à audit externe...',
                'changeLog' => 'Révision complète suite à l\'audit d\'accessibilité externe, ajout de nouvelles mesures correctives.',
                'isCurrent' => false,
                'createdAt' => new DateTimeImmutable('-3 months'),
                'fileSize' => 10100,
            ],
            [
                'document' => DocumentFixtures::ACCESSIBILITY_POLICY_REFERENCE,
                'version' => '1.3',
                'title' => 'Politique d\'Accessibilité Numérique',
                'content' => null, // Current version
                'changeLog' => 'Mise à jour du calendrier de mise en conformité, ajout des contacts de support.',
                'isCurrent' => true,
                'createdAt' => new DateTimeImmutable('-2 months'),
                'fileSize' => 10500,
            ],
        ];

        // Version history for Quality Manual
        $qualityVersions = [
            [
                'document' => DocumentFixtures::QUALITY_MANUAL_REFERENCE,
                'version' => '1.0',
                'title' => 'Manuel Qualité Qualiopi',
                'content' => 'Version initiale du manuel qualité...',
                'changeLog' => 'Création du manuel qualité initial pour la certification Qualiopi.',
                'isCurrent' => false,
                'createdAt' => new DateTimeImmutable('-18 months'),
                'fileSize' => 45000,
            ],
            [
                'document' => DocumentFixtures::QUALITY_MANUAL_REFERENCE,
                'version' => '2.0',
                'title' => 'Manuel Qualité Qualiopi',
                'content' => 'Révision majeure post-certification...',
                'changeLog' => 'Révision majeure suite à l\'obtention de la certification Qualiopi. Intégration des retours d\'audit et des améliorations identifiées.',
                'isCurrent' => false,
                'createdAt' => new DateTimeImmutable('-12 months'),
                'fileSize' => 52000,
            ],
            [
                'document' => DocumentFixtures::QUALITY_MANUAL_REFERENCE,
                'version' => '2.5',
                'title' => 'Manuel Qualité Qualiopi',
                'content' => 'Mise à jour continue...',
                'changeLog' => 'Mise à jour des indicateurs de performance, ajout de nouvelles procédures d\'amélioration continue.',
                'isCurrent' => false,
                'createdAt' => new DateTimeImmutable('-6 months'),
                'fileSize' => 54500,
            ],
            [
                'document' => DocumentFixtures::QUALITY_MANUAL_REFERENCE,
                'version' => '3.0',
                'title' => 'Manuel Qualité Qualiopi',
                'content' => null, // Current version
                'changeLog' => 'Révision annuelle majeure : mise à jour de tous les processus, intégration des nouveaux indicateurs de performance, révision de la politique qualité.',
                'isCurrent' => true,
                'createdAt' => new DateTimeImmutable('-1 month'),
                'fileSize' => 58200,
            ],
        ];

        // Version history for Student Handbook
        $handbookVersions = [
            [
                'document' => DocumentFixtures::STUDENT_HANDBOOK_REFERENCE,
                'version' => '1.0',
                'title' => 'Livret d\'Accueil Apprenant 2025',
                'content' => null, // Current version
                'changeLog' => 'Création du nouveau livret d\'accueil pour l\'année 2025. Mise à jour complète des informations pratiques, nouveaux services, et mise en conformité avec les dernières réglementations.',
                'isCurrent' => true,
                'createdAt' => new DateTimeImmutable('-2 weeks'),
                'fileSize' => 25600,
            ],
        ];

        // Version history for Internal Regulations
        $regulationsVersions = [
            [
                'document' => DocumentFixtures::INTERNAL_REGULATIONS_REFERENCE,
                'version' => '1.0',
                'title' => 'Règlement Intérieur Formation',
                'content' => 'Version initiale du règlement...',
                'changeLog' => 'Création du règlement intérieur conforme au Code du travail.',
                'isCurrent' => false,
                'createdAt' => new DateTimeImmutable('-12 months'),
                'fileSize' => 12000,
            ],
            [
                'document' => DocumentFixtures::INTERNAL_REGULATIONS_REFERENCE,
                'version' => '1.2',
                'title' => 'Règlement Intérieur Formation',
                'content' => 'Mise à jour des sanctions...',
                'changeLog' => 'Révision des procédures disciplinaires et mise à jour des consignes de sécurité.',
                'isCurrent' => false,
                'createdAt' => new DateTimeImmutable('-9 months'),
                'fileSize' => 13200,
            ],
            [
                'document' => DocumentFixtures::INTERNAL_REGULATIONS_REFERENCE,
                'version' => '1.5',
                'title' => 'Règlement Intérieur Formation',
                'content' => null, // Current version
                'changeLog' => 'Ajout des règles spécifiques à la formation à distance, mise à jour des horaires et des modalités d\'évaluation.',
                'isCurrent' => true,
                'createdAt' => new DateTimeImmutable('-6 months'),
                'fileSize' => 14800,
            ],
        ];

        // Version history for Privacy Policy
        $privacyVersions = [
            [
                'document' => DocumentFixtures::PRIVACY_POLICY_REFERENCE,
                'version' => '1.0',
                'title' => 'Politique de Confidentialité et Protection des Données',
                'content' => 'Version initiale RGPD...',
                'changeLog' => 'Création de la politique de confidentialité conforme RGPD.',
                'isCurrent' => false,
                'createdAt' => new DateTimeImmutable('-8 months'),
                'fileSize' => 16500,
            ],
            [
                'document' => DocumentFixtures::PRIVACY_POLICY_REFERENCE,
                'version' => '2.0',
                'title' => 'Politique de Confidentialité et Protection des Données',
                'content' => null, // Current version
                'changeLog' => 'Révision majeure : mise à jour des finalités de traitement, ajout des nouveaux droits des personnes, précision sur les durées de conservation.',
                'isCurrent' => true,
                'createdAt' => new DateTimeImmutable('-4 months'),
                'fileSize' => 18900,
            ],
        ];

        // Version history for Evaluation Procedure
        $evaluationVersions = [
            [
                'document' => DocumentFixtures::EVALUATION_PROCEDURE_REFERENCE,
                'version' => '1.0',
                'title' => 'Procédure d\'Évaluation des Apprentissages',
                'content' => 'Version initiale de la procédure...',
                'changeLog' => 'Création de la procédure d\'évaluation standardisée.',
                'isCurrent' => false,
                'createdAt' => new DateTimeImmutable('-6 months'),
                'fileSize' => 22000,
            ],
            [
                'document' => DocumentFixtures::EVALUATION_PROCEDURE_REFERENCE,
                'version' => '1.1',
                'title' => 'Procédure d\'Évaluation des Apprentissages',
                'content' => 'Ajout des grilles d\'évaluation...',
                'changeLog' => 'Intégration des nouvelles grilles d\'évaluation par compétences.',
                'isCurrent' => false,
                'createdAt' => new DateTimeImmutable('-4 months'),
                'fileSize' => 24500,
            ],
            [
                'document' => DocumentFixtures::EVALUATION_PROCEDURE_REFERENCE,
                'version' => '1.2',
                'title' => 'Procédure d\'Évaluation des Apprentissages',
                'content' => null, // Current version
                'changeLog' => 'Révision des modalités de rattrapage et mise à jour des critères d\'évaluation continue.',
                'isCurrent' => true,
                'createdAt' => new DateTimeImmutable('-3 weeks'),
                'fileSize' => 26200,
            ],
        ];

        // Combine all versions
        $allVersions = array_merge(
            $cgvfVersions,
            $accessibilityVersions,
            $qualityVersions,
            $handbookVersions,
            $regulationsVersions,
            $privacyVersions,
            $evaluationVersions,
        );

        foreach ($allVersions as $versionData) {
            $version = new DocumentVersion();
            $version->setDocument($this->getReference($versionData['document'], Document::class))
                ->setVersion($versionData['version'])
                ->setTitle($versionData['title'])
                ->setContent($versionData['content'])
                ->setChangeLog($versionData['changeLog'])
                ->setIsCurrent($versionData['isCurrent'])
                ->setCreatedAt($versionData['createdAt'])
                ->setFileSize($versionData['fileSize'])
            ;

            // Generate checksum for the version
            $version->generateChecksum();

            $manager->persist($version);
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
