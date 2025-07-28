<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Document\DocumentTemplate;
use App\Entity\Document\DocumentType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Document Template Fixtures - Creates reusable document templates.
 *
 * Provides comprehensive template examples for efficient document
 * creation and consistency across the organization.
 */
class DocumentTemplateFixtures extends Fixture implements DependentFixtureInterface
{
    public const LEGAL_TEMPLATE_REFERENCE = 'document-template-legal';

    public const POLICY_TEMPLATE_REFERENCE = 'document-template-policy';

    public const PROCEDURE_TEMPLATE_REFERENCE = 'document-template-procedure';

    public const HANDBOOK_TEMPLATE_REFERENCE = 'document-template-handbook';

    public function load(ObjectManager $manager): void
    {
        $templates = [
            [
                'name' => 'Modèle Document Légal',
                'slug' => 'modele-document-legal',
                'description' => 'Modèle standardisé pour la création de documents légaux et réglementaires',
                'documentType' => DocumentTypeFixtures::LEGAL_TYPE_REFERENCE,
                'templateContent' => $this->getLegalTemplateContent(),
                'placeholders' => [
                    'document_title' => [
                        'type' => 'string',
                        'label' => 'Titre du document',
                        'required' => true,
                        'description' => 'Titre principal du document légal',
                    ],
                    'legal_reference' => [
                        'type' => 'string',
                        'label' => 'Référence légale',
                        'required' => true,
                        'description' => 'Code ou article de loi de référence',
                    ],
                    'effective_date' => [
                        'type' => 'date',
                        'label' => 'Date d\'entrée en vigueur',
                        'required' => true,
                        'description' => 'Date à partir de laquelle le document s\'applique',
                    ],
                    'review_date' => [
                        'type' => 'date',
                        'label' => 'Date de révision prévue',
                        'required' => false,
                        'description' => 'Prochaine date de révision du document',
                    ],
                    'approval_authority' => [
                        'type' => 'string',
                        'label' => 'Autorité d\'approbation',
                        'required' => true,
                        'description' => 'Personne ou instance ayant approuvé le document',
                    ],
                ],
                'defaultMetadata' => [
                    'document_category' => 'legal',
                    'requires_approval' => true,
                    'public_access' => true,
                    'version_control' => true,
                ],
                'configuration' => [
                    'auto_numbering' => true,
                    'approval_workflow' => true,
                    'version_tracking' => true,
                    'expiration_alerts' => true,
                ],
                'icon' => 'fas fa-gavel',
                'color' => '#dc3545',
                'isDefault' => true,
                'sortOrder' => 1,
                'reference' => self::LEGAL_TEMPLATE_REFERENCE,
            ],
            [
                'name' => 'Modèle Politique Interne',
                'slug' => 'modele-politique-interne',
                'description' => 'Modèle pour les politiques internes et procédures organisationnelles',
                'documentType' => DocumentTypeFixtures::POLICY_TYPE_REFERENCE,
                'templateContent' => $this->getPolicyTemplateContent(),
                'placeholders' => [
                    'policy_title' => [
                        'type' => 'string',
                        'label' => 'Titre de la politique',
                        'required' => true,
                        'description' => 'Nom de la politique interne',
                    ],
                    'department' => [
                        'type' => 'select',
                        'label' => 'Département responsable',
                        'required' => true,
                        'options' => ['RH', 'Formation', 'Qualité', 'Direction', 'IT'],
                        'description' => 'Département pilote de cette politique',
                    ],
                    'policy_owner' => [
                        'type' => 'string',
                        'label' => 'Responsable de la politique',
                        'required' => true,
                        'description' => 'Personne responsable de la mise en œuvre',
                    ],
                    'scope' => [
                        'type' => 'text',
                        'label' => 'Périmètre d\'application',
                        'required' => true,
                        'description' => 'À qui s\'applique cette politique',
                    ],
                    'review_frequency' => [
                        'type' => 'select',
                        'label' => 'Fréquence de révision',
                        'required' => true,
                        'options' => ['Annuelle', 'Biannuelle', 'Triennale'],
                        'description' => 'Fréquence de révision de la politique',
                    ],
                ],
                'defaultMetadata' => [
                    'document_category' => 'internal_policy',
                    'requires_approval' => true,
                    'public_access' => false,
                    'department_access' => true,
                ],
                'configuration' => [
                    'approval_workflow' => true,
                    'department_notification' => true,
                    'training_required' => false,
                ],
                'icon' => 'fas fa-clipboard-list',
                'color' => '#6f42c1',
                'isDefault' => true,
                'sortOrder' => 2,
                'reference' => self::POLICY_TEMPLATE_REFERENCE,
            ],
            [
                'name' => 'Modèle Procédure Opérationnelle',
                'slug' => 'modele-procedure-operationnelle',
                'description' => 'Modèle standardisé pour les procédures détaillées et guides opérationnels',
                'documentType' => DocumentTypeFixtures::PROCEDURE_TYPE_REFERENCE,
                'templateContent' => $this->getProcedureTemplateContent(),
                'placeholders' => [
                    'procedure_title' => [
                        'type' => 'string',
                        'label' => 'Titre de la procédure',
                        'required' => true,
                        'description' => 'Nom de la procédure',
                    ],
                    'process_owner' => [
                        'type' => 'string',
                        'label' => 'Responsable du processus',
                        'required' => true,
                        'description' => 'Personne en charge du processus',
                    ],
                    'complexity_level' => [
                        'type' => 'select',
                        'label' => 'Niveau de complexité',
                        'required' => true,
                        'options' => ['Simple', 'Moyen', 'Complexe'],
                        'description' => 'Niveau de difficulté de la procédure',
                    ],
                    'target_audience' => [
                        'type' => 'text',
                        'label' => 'Public cible',
                        'required' => true,
                        'description' => 'Personnes concernées par cette procédure',
                    ],
                    'prerequisites' => [
                        'type' => 'text',
                        'label' => 'Prérequis',
                        'required' => false,
                        'description' => 'Connaissances ou autorisations nécessaires',
                    ],
                    'tools_required' => [
                        'type' => 'text',
                        'label' => 'Outils nécessaires',
                        'required' => false,
                        'description' => 'Matériel ou logiciels requis',
                    ],
                ],
                'defaultMetadata' => [
                    'document_category' => 'procedure',
                    'requires_approval' => false,
                    'public_access' => false,
                    'step_by_step' => true,
                ],
                'configuration' => [
                    'multimedia_support' => true,
                    'user_feedback' => true,
                    'version_tracking' => true,
                    'usage_analytics' => true,
                ],
                'icon' => 'fas fa-list-ol',
                'color' => '#28a745',
                'isDefault' => true,
                'sortOrder' => 3,
                'reference' => self::PROCEDURE_TEMPLATE_REFERENCE,
            ],
            [
                'name' => 'Modèle Livret d\'Accueil',
                'slug' => 'modele-livret-accueil',
                'description' => 'Modèle pour les livrets d\'accueil et documents d\'orientation',
                'documentType' => DocumentTypeFixtures::HANDBOOK_TYPE_REFERENCE,
                'templateContent' => $this->getHandbookTemplateContent(),
                'placeholders' => [
                    'handbook_title' => [
                        'type' => 'string',
                        'label' => 'Titre du livret',
                        'required' => true,
                        'description' => 'Titre du livret d\'accueil',
                    ],
                    'academic_year' => [
                        'type' => 'string',
                        'label' => 'Année académique',
                        'required' => true,
                        'description' => 'Année de validité du livret',
                    ],
                    'target_audience' => [
                        'type' => 'select',
                        'label' => 'Public cible',
                        'required' => true,
                        'options' => ['Nouveaux apprenants', 'Stagiaires', 'Alternants', 'Formateurs'],
                        'description' => 'Destinataires du livret',
                    ],
                    'language' => [
                        'type' => 'select',
                        'label' => 'Langue',
                        'required' => true,
                        'options' => ['Français', 'English', 'Español'],
                        'description' => 'Langue du document',
                    ],
                    'contact_person' => [
                        'type' => 'string',
                        'label' => 'Personne de contact',
                        'required' => true,
                        'description' => 'Référent pour questions',
                    ],
                    'contact_email' => [
                        'type' => 'email',
                        'label' => 'Email de contact',
                        'required' => true,
                        'description' => 'Adresse email de contact',
                    ],
                    'contact_phone' => [
                        'type' => 'string',
                        'label' => 'Téléphone',
                        'required' => false,
                        'description' => 'Numéro de téléphone',
                    ],
                ],
                'defaultMetadata' => [
                    'document_category' => 'student_handbook',
                    'requires_approval' => true,
                    'public_access' => true,
                    'multilingual' => false,
                ],
                'configuration' => [
                    'downloadable' => true,
                    'print_friendly' => true,
                    'mobile_optimized' => true,
                    'accessibility_compliant' => true,
                ],
                'icon' => 'fas fa-graduation-cap',
                'color' => '#007bff',
                'isDefault' => true,
                'sortOrder' => 4,
                'reference' => self::HANDBOOK_TEMPLATE_REFERENCE,
            ],
            [
                'name' => 'Modèle Document Qualité',
                'slug' => 'modele-document-qualite',
                'description' => 'Modèle pour les documents du système qualité Qualiopi',
                'documentType' => DocumentTypeFixtures::QUALITY_TYPE_REFERENCE,
                'templateContent' => $this->getQualityTemplateContent(),
                'placeholders' => [
                    'quality_doc_title' => [
                        'type' => 'string',
                        'label' => 'Titre du document qualité',
                        'required' => true,
                        'description' => 'Nom du document qualité',
                    ],
                    'qualiopi_criterion' => [
                        'type' => 'select',
                        'label' => 'Critère Qualiopi',
                        'required' => true,
                        'options' => [
                            'Critère 1 - Information',
                            'Critère 2 - Objectifs',
                            'Critère 3 - Adaptation',
                            'Critère 4 - Moyens',
                            'Critère 5 - Qualification',
                            'Critère 6 - Environnement',
                            'Critère 7 - Évaluation',
                        ],
                        'description' => 'Critère Qualiopi principal couvert',
                    ],
                    'responsible_person' => [
                        'type' => 'string',
                        'label' => 'Responsable qualité',
                        'required' => true,
                        'description' => 'Responsable du document qualité',
                    ],
                    'audit_frequency' => [
                        'type' => 'select',
                        'label' => 'Fréquence d\'audit',
                        'required' => true,
                        'options' => ['Annuelle', 'Semestrielle', 'Trimestrielle'],
                        'description' => 'Fréquence de révision/audit',
                    ],
                ],
                'defaultMetadata' => [
                    'document_category' => 'quality',
                    'requires_approval' => true,
                    'public_access' => false,
                    'qualiopi_compliant' => true,
                ],
                'configuration' => [
                    'audit_trail' => true,
                    'kpi_tracking' => true,
                    'continuous_improvement' => true,
                    'compliance_monitoring' => true,
                ],
                'icon' => 'fas fa-certificate',
                'color' => '#ffc107',
                'isDefault' => false,
                'sortOrder' => 5,
                'reference' => 'document-template-quality',
            ],
        ];

        foreach ($templates as $templateData) {
            $template = new DocumentTemplate();
            $template->setName($templateData['name'])
                ->setSlug($templateData['slug'])
                ->setDescription($templateData['description'])
                ->setDocumentType($this->getReference($templateData['documentType'], DocumentType::class))
                ->setTemplateContent($templateData['templateContent'])
                ->setPlaceholders($templateData['placeholders'])
                ->setDefaultMetadata($templateData['defaultMetadata'])
                ->setConfiguration($templateData['configuration'])
                ->setIcon($templateData['icon'])
                ->setColor($templateData['color'])
                ->setIsDefault($templateData['isDefault'])
                ->setSortOrder($templateData['sortOrder'])
                ->setIsActive(true)
                ->setUsageCount(0)
            ;

            $manager->persist($template);
            $this->addReference($templateData['reference'], $template);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            DocumentTypeFixtures::class,
        ];
    }

    private function getLegalTemplateContent(): string
    {
        return <<<'EOF'
# {{document_title}}

## Article 1 - Objet et champ d'application

Le présent document définit les règles et dispositions applicables conformément à {{legal_reference}}.

## Article 2 - Définitions

Les termes utilisés dans le présent document ont la signification suivante :
- [Définition 1]
- [Définition 2]
- [Définition 3]

## Article 3 - Dispositions générales

### 3.1 Principes généraux
[Détailler les principes fondamentaux]

### 3.2 Champ d'application
[Préciser le périmètre d'application]

## Article 4 - Obligations des parties

### 4.1 Obligations de l'organisme
- [Obligation 1]
- [Obligation 2]
- [Obligation 3]

### 4.2 Obligations des bénéficiaires
- [Obligation 1]
- [Obligation 2]
- [Obligation 3]

## Article 5 - Modalités d'application

[Détailler les modalités pratiques d'application]

## Article 6 - Sanctions et recours

[Définir les sanctions possibles et les procédures de recours]

## Article 7 - Dispositions finales

### 7.1 Entrée en vigueur
Le présent document entre en vigueur le {{effective_date}}.

### 7.2 Révision
Une révision est prévue le {{review_date}}.

### 7.3 Approbation
Document approuvé par {{approval_authority}}.

---
*Document conforme à {{legal_reference}}*
*Date d'entrée en vigueur : {{effective_date}}*
EOF;
    }

    private function getPolicyTemplateContent(): string
    {
        return <<<'EOF'
# {{policy_title}}

## 1. Objet de la politique

Cette politique définit les orientations et principes de {{department}} concernant {{policy_title}}.

## 2. Périmètre d'application

### 2.1 Qui est concerné
{{scope}}

### 2.2 Domaines couverts
- [Domaine 1]
- [Domaine 2]
- [Domaine 3]

## 3. Principes directeurs

### 3.1 Valeurs fondamentales
- [Valeur 1]
- [Valeur 2]
- [Valeur 3]

### 3.2 Objectifs visés
- [Objectif 1]
- [Objectif 2]
- [Objectif 3]

## 4. Dispositions générales

### 4.1 Règles applicables
[Détailler les règles principales]

### 4.2 Bonnes pratiques
[Lister les bonnes pratiques recommandées]

## 5. Responsabilités

### 5.1 Responsable de la politique
{{policy_owner}} est responsable de la mise en œuvre de cette politique.

### 5.2 Responsabilités des managers
- [Responsabilité 1]
- [Responsabilité 2]

### 5.3 Responsabilités des collaborateurs
- [Responsabilité 1]
- [Responsabilité 2]

## 6. Mise en œuvre

### 6.1 Déploiement
[Détailler les étapes de déploiement]

### 6.2 Formation
[Préciser les besoins de formation]

### 6.3 Communication
[Définir le plan de communication]

## 7. Suivi et évaluation

### 7.1 Indicateurs de suivi
- [Indicateur 1]
- [Indicateur 2]

### 7.2 Fréquence de révision
Cette politique est révisée {{review_frequency}}.

### 7.3 Amélioration continue
[Processus d'amélioration continue]

---
*Politique validée par {{policy_owner}}*
*Département responsable : {{department}}*
EOF;
    }

    private function getProcedureTemplateContent(): string
    {
        return <<<'EOF'
# {{procedure_title}}

## 1. Information générale

### 1.1 Objectif de la procédure
[Décrire l'objectif principal de la procédure]

### 1.2 Périmètre d'application
{{target_audience}}

### 1.3 Responsable du processus
{{process_owner}}

### 1.4 Niveau de complexité
{{complexity_level}}

## 2. Prérequis

### 2.1 Connaissances requises
{{prerequisites}}

### 2.2 Outils nécessaires
{{tools_required}}

### 2.3 Autorisations requises
[Lister les autorisations nécessaires]

## 3. Procédure étape par étape

### Étape 1 : [Titre de l'étape]
**Action :** [Description de l'action à réaliser]
**Durée estimée :** [Temps nécessaire]
**Acteur :** [Qui réalise cette étape]
**Outils :** [Outils utilisés]
**Points de contrôle :** [Éléments à vérifier]

### Étape 2 : [Titre de l'étape]
**Action :** [Description de l'action à réaliser]
**Durée estimée :** [Temps nécessaire]
**Acteur :** [Qui réalise cette étape]
**Outils :** [Outils utilisés]
**Points de contrôle :** [Éléments à vérifier]

### Étape 3 : [Titre de l'étape]
**Action :** [Description de l'action à réaliser]
**Durée estimée :** [Temps nécessaire]
**Acteur :** [Qui réalise cette étape]
**Outils :** [Outils utilisés]
**Points de contrôle :** [Éléments à vérifier]

## 4. Points d'attention

### 4.1 Risques identifiés
- [Risque 1 et sa prévention]
- [Risque 2 et sa prévention]

### 4.2 Bonnes pratiques
- [Bonne pratique 1]
- [Bonne pratique 2]

### 4.3 Erreurs courantes à éviter
- [Erreur 1]
- [Erreur 2]

## 5. Contrôles et validation

### 5.1 Points de contrôle
- [Contrôle 1]
- [Contrôle 2]

### 5.2 Critères de réussite
- [Critère 1]
- [Critère 2]

### 5.3 Actions en cas d'anomalie
[Décrire la procédure de résolution des problèmes]

## 6. Documentation et traçabilité

### 6.1 Documents à produire
- [Document 1]
- [Document 2]

### 6.2 Archivage
[Modalités d'archivage des documents]

## 7. Amélioration continue

### 7.1 Retours d'expérience
[Comment collecter les retours]

### 7.2 Mise à jour de la procédure
[Processus de mise à jour]

---
*Procédure validée par {{process_owner}}*
*Niveau de complexité : {{complexity_level}}*
EOF;
    }

    private function getHandbookTemplateContent(): string
    {
        return <<<'EOF'
# {{handbook_title}}

Bienvenue ! Ce livret vous accompagne dans vos premiers pas.

## Présentation de l'organisme

### Qui sommes-nous ?
[Présentation de l'organisme de formation]

### Nos valeurs
- **Excellence** : [Description]
- **Innovation** : [Description]
- **Inclusion** : [Description]

### Notre équipe
[Présentation de l'équipe pédagogique]

## Votre parcours

### Avant le début
1. [Étape 1]
2. [Étape 2]
3. [Étape 3]

### Pendant la formation
- [Information 1]
- [Information 2]
- [Information 3]

### Après la formation
- [Information 1]
- [Information 2]
- [Information 3]

## Informations pratiques

### Horaires
- [Horaires d'ouverture]
- [Horaires de formation]

### Locaux
**Adresse principale**
[Adresse complète]

### Services disponibles
- [Service 1]
- [Service 2]
- [Service 3]

## Vos interlocuteurs

### Équipe pédagogique
- **Contact principal** : {{contact_person}}
- **Email** : {{contact_email}}
- **Téléphone** : {{contact_phone}}

### Autres contacts
- [Contact 1]
- [Contact 2]

## Droits et devoirs

### Vos droits
- [Droit 1]
- [Droit 2]
- [Droit 3]

### Vos devoirs
- [Devoir 1]
- [Devoir 2]
- [Devoir 3]

## Financement et démarches

### Modalités de financement
- [Modalité 1]
- [Modalité 2]
- [Modalité 3]

### Démarches administratives
- [Démarche 1]
- [Démarche 2]

## Ressources utiles

### Documentation
- [Ressource 1]
- [Ressource 2]

### Liens utiles
- [Lien 1]
- [Lien 2]

---
*Année académique : {{academic_year}}*
*Public cible : {{target_audience}}*
*Langue : {{language}}*
EOF;
    }

    private function getQualityTemplateContent(): string
    {
        return <<<'EOF'
# {{quality_doc_title}}

## 1. Objet du document

Ce document s'inscrit dans le cadre du système de management de la qualité et répond au {{qualiopi_criterion}}.

## 2. Périmètre d'application

### 2.1 Domaine concerné
[Préciser le domaine d'application]

### 2.2 Processus impactés
- [Processus 1]
- [Processus 2]
- [Processus 3]

## 3. Références réglementaires

### 3.1 Référentiel Qualiopi
{{qualiopi_criterion}}

### 3.2 Autres références
- [Référence 1]
- [Référence 2]

## 4. Définitions et acronymes

### 4.1 Définitions
- [Terme 1] : [Définition]
- [Terme 2] : [Définition]

### 4.2 Acronymes
- [Acronyme 1] : [Signification]
- [Acronyme 2] : [Signification]

## 5. Processus qualité

### 5.1 Description du processus
[Décrire le processus principal]

### 5.2 Étapes clés
1. [Étape 1]
2. [Étape 2]
3. [Étape 3]

### 5.3 Acteurs impliqués
- [Acteur 1] : [Rôle]
- [Acteur 2] : [Rôle]

## 6. Indicateurs de performance

### 6.1 Indicateurs de qualité
- [Indicateur 1] : [Définition et cible]
- [Indicateur 2] : [Définition et cible]

### 6.2 Méthodes de mesure
[Décrire les méthodes de collecte et d'analyse]

### 6.3 Fréquence de suivi
[Préciser la fréquence de suivi des indicateurs]

## 7. Responsabilités

### 7.1 Responsable qualité
{{responsible_person}} est responsable de ce document et de son application.

### 7.2 Responsabilités opérationnelles
- [Responsabilité 1]
- [Responsabilité 2]

### 7.3 Responsabilités de direction
- [Responsabilité 1]
- [Responsabilité 2]

## 8. Amélioration continue

### 8.1 Processus d'amélioration
[Décrire le processus d'amélioration continue]

### 8.2 Gestion des non-conformités
[Processus de traitement des non-conformités]

### 8.3 Actions correctives et préventives
[Méthodologie des actions d'amélioration]

## 9. Formation et sensibilisation

### 9.1 Besoins de formation
[Identifier les besoins de formation]

### 9.2 Plan de sensibilisation
[Décrire le plan de sensibilisation]

## 10. Audit et révision

### 10.1 Fréquence d'audit
{{audit_frequency}}

### 10.2 Processus de révision
[Décrire le processus de révision du document]

### 10.3 Validation
[Processus de validation des modifications]

---
*Document qualité conforme au {{qualiopi_criterion}}*
*Responsable : {{responsible_person}}*
*Fréquence d'audit : {{audit_frequency}}*
EOF;
    }
}
