<?php

namespace App\DataFixtures;

use App\Entity\Training\Category;
use App\Entity\Training\Formation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Formation fixtures for EPROFOS platform
 * 
 * Creates realistic professional training courses with detailed content,
 * objectives, prerequisites, programs, and pricing information.
 */
class FormationFixtures extends Fixture implements DependentFixtureInterface
{
    private SluggerInterface $slugger;

    public function __construct(SluggerInterface $slugger)
    {
        $this->slugger = $slugger;
    }

    /**
     * Load formation fixtures
     */
    public function load(ObjectManager $manager): void
    {
        $formations = [
            // Informatique et Numérique
            [
                'title' => 'Développement Web avec PHP et Symfony',
                'description' => 'Formation complète au développement web moderne avec PHP 8.3 et le framework Symfony 7. Apprenez à créer des applications web robustes et sécurisées.',
                'objectives' => "• Maîtriser les concepts avancés de PHP 8.3\n• Développer des applications avec Symfony 7\n• Implémenter des API REST\n• Gérer les bases de données avec Doctrine\n• Appliquer les bonnes pratiques de sécurité",
                'operationalObjectives' => [
                    'Développer une application web complète avec Symfony 7',
                    'Créer et configurer des entités Doctrine avec relations',
                    'Implémenter un système d\'authentification sécurisé',
                    'Développer des API REST documentées avec API Platform',
                    'Mettre en place des tests unitaires et fonctionnels',
                    'Déployer une application sur un serveur de production'
                ],
                'evaluableObjectives' => [
                    'Créer une application e-commerce fonctionnelle en moins de 40h',
                    'Atteindre 90% de couverture de code par les tests',
                    'Implémenter une API REST avec temps de réponse < 200ms',
                    'Sécuriser l\'application selon les standards OWASP',
                    'Optimiser les performances avec un score PageSpeed > 85'
                ],
                'evaluationCriteria' => [
                    'QCM final avec 80% de bonnes réponses minimum',
                    'Projet d\'application web évalué sur 20 critères techniques',
                    'Code review avec respect des standards PSR',
                    'Présentation technique de 15 minutes devant jury',
                    'Documentation technique complète et claire'
                ],
                'successIndicators' => [
                    'Taux de réussite des participants > 90%',
                    'Note moyenne projet > 16/20',
                    'Satisfaction formation > 4.5/5',
                    'Taux d\'insertion professionnelle > 85% à 6 mois',
                    'Nombre d\'applications déployées en production'
                ],
                'prerequisites' => 'Connaissances de base en programmation et HTML/CSS. Expérience préalable en PHP recommandée.',
                'program' => "Module 1: Fondamentaux PHP 8.3 (14h)\n- Nouveautés PHP 8.3\n- Programmation orientée objet avancée\n- Gestion des erreurs et exceptions\n\nModule 2: Framework Symfony 7 (21h)\n- Architecture MVC\n- Routing et contrôleurs\n- Templates Twig\n- Formulaires et validation\n\nModule 3: Base de données et Doctrine (14h)\n- ORM Doctrine\n- Migrations et fixtures\n- Relations entre entités\n\nModule 4: Sécurité et API (14h)\n- Authentification et autorisation\n- Développement d'API REST\n- Tests unitaires et fonctionnels",
                'durationHours' => 63,
                'price' => '2890.00',
                'level' => 'Intermédiaire',
                'format' => 'Présentiel',
                'isFeatured' => true,
                'image' => 'formation-php-symfony.jpg',
                'category' => CategoryFixtures::CATEGORY_INFORMATIQUE,
                'targetAudience' => 'Développeurs web, chefs de projet technique, étudiants en informatique souhaitant se spécialiser dans le développement web moderne',
                'accessModalities' => 'Inscription en ligne sur notre site web. Délai d\'accès : 2 semaines. Entretien technique préalable recommandé pour valider les prérequis.',
                'handicapAccessibility' => 'Locaux accessibles PMR. Adaptation pédagogique possible selon les besoins. Contact référent handicap : handicap@eprofos.fr - Tél : 01.42.85.96.32',
                'teachingMethods' => 'Formation présentielle avec alternance théorie/pratique (70% pratique), ateliers de développement hands-on, projets individuels et en équipe, code reviews',
                'evaluationMethods' => 'QCM de validation des connaissances théoriques, projet pratique de développement d\'application web complète, soutenance technique devant jury',
                'contactInfo' => 'Référent pédagogique : sophie.martin@eprofos.fr - Tél : 01.42.85.96.15 | Référent administratif : admin@eprofos.fr - Tél : 01.42.85.96.10',
                'trainingLocation' => 'EPROFOS - Centre de formation - 123 Avenue de la Formation, 75001 Paris - Salles informatiques équipées (20 postes)',
                'fundingModalities' => 'Éligible CPF (code 237359), financement OPCO, prise en charge Pôle Emploi, tarif préférentiel entreprise (-15% à partir de 3 inscrits)',
            ],
            [
                'title' => 'Cybersécurité et Protection des Données',
                'description' => 'Formation essentielle pour comprendre et implémenter les mesures de cybersécurité dans votre organisation. Protégez vos données et systèmes contre les menaces actuelles.',
                'objectives' => "• Identifier les principales menaces cybersécurité\n• Mettre en place des mesures de protection\n• Gérer les incidents de sécurité\n• Sensibiliser les équipes aux bonnes pratiques\n• Respecter le RGPD et les réglementations",
                'prerequisites' => 'Connaissances de base en informatique et réseaux.',
                'program' => "Module 1: Panorama des menaces (7h)\n- Types d'attaques courantes\n- Vulnérabilités système\n- Ingénierie sociale\n\nModule 2: Mesures de protection (14h)\n- Pare-feu et antivirus\n- Chiffrement des données\n- Sauvegarde et récupération\n\nModule 3: Gestion des incidents (7h)\n- Détection d'intrusion\n- Procédures de réponse\n- Analyse forensique\n\nModule 4: Conformité RGPD (7h)\n- Obligations légales\n- Protection des données personnelles\n- Audit de conformité",
                'durationHours' => 35,
                'price' => '1890.00',
                'level' => 'Débutant',
                'format' => 'Hybride',
                'isFeatured' => false,
                'image' => 'formation-cybersecurite.png',
                'category' => CategoryFixtures::CATEGORY_INFORMATIQUE,
                'targetAudience' => 'Responsables informatiques, administrateurs système, DPO, dirigeants d\'entreprise, consultants en sécurité',
                'accessModalities' => 'Inscription en ligne. Délai d\'accès : 3 semaines. Formation hybride avec 50% en présentiel et 50% à distance.',
                'handicapAccessibility' => 'Formation accessible aux personnes en situation de handicap. Supports adaptés disponibles. Contact : handicap@eprofos.fr',
                'teachingMethods' => 'Formation hybride alternant présentiel et distanciel, études de cas réels, simulations d\'attaques, ateliers pratiques de mise en sécurité',
                'evaluationMethods' => 'QCM de validation, audit de sécurité pratique sur environnement de test, présentation d\'un plan de sécurisation',
                'contactInfo' => 'Référent pédagogique : marc.dubois@eprofos.fr - Tél : 01.42.85.96.18 | Support technique : support@eprofos.fr',
                'trainingLocation' => 'Formation hybride : EPROFOS Paris + plateforme e-learning sécurisée accessible 24h/24',
                'fundingModalities' => 'Éligible CPF, OPCO, financement entreprise, prise en charge spécifique secteur public disponible',
            ],
            [
                'title' => 'Maîtrise d\'Excel Avancé et Power BI',
                'description' => 'Devenez expert en analyse de données avec Excel avancé et Power BI. Créez des tableaux de bord dynamiques et des rapports professionnels.',
                'objectives' => "• Maîtriser les fonctions avancées d'Excel\n• Créer des tableaux croisés dynamiques complexes\n• Développer des macros VBA\n• Concevoir des tableaux de bord avec Power BI\n• Automatiser les rapports de gestion",
                'prerequisites' => 'Bonne maîtrise d\'Excel niveau intermédiaire.',
                'program' => "Module 1: Excel Avancé (14h)\n- Fonctions complexes et imbriquées\n- Tableaux croisés dynamiques\n- Analyse de données\n- Graphiques avancés\n\nModule 2: VBA et Macros (7h)\n- Introduction à VBA\n- Automatisation des tâches\n- Création de formulaires\n\nModule 3: Power BI (14h)\n- Interface et navigation\n- Connexion aux sources de données\n- Modélisation des données\n- Création de visualisations\n\nModule 4: Tableaux de bord (7h)\n- Design et ergonomie\n- Interactivité\n- Partage et collaboration",
                'durationHours' => 42,
                'price' => '1990.00',
                'level' => 'Avancé',
                'format' => 'Présentiel',
                'isFeatured' => true,
                'image' => 'formation-excel-powerbi.jpg',
                'category' => CategoryFixtures::CATEGORY_INFORMATIQUE,
                'targetAudience' => 'Contrôleurs de gestion, analystes financiers, chefs de projet, responsables reporting, consultants en organisation',
                'accessModalities' => 'Inscription en ligne avec test de niveau Excel préalable. Délai d\'accès : 2 semaines. Groupes limités à 8 participants.',
                'handicapAccessibility' => 'Locaux accessibles PMR. Logiciels adaptés disponibles pour déficients visuels. Contact : handicap@eprofos.fr',
                'teachingMethods' => 'Formation présentielle intensive, cas pratiques sur données réelles d\'entreprise, projets individualisés, coaching personnalisé',
                'evaluationMethods' => 'Évaluation continue sur exercices pratiques, projet final de création de tableau de bord complet, certification interne EPROFOS',
                'contactInfo' => 'Référent pédagogique : claire.bernard@eprofos.fr - Tél : 01.42.85.96.22 | Assistance technique : tech@eprofos.fr',
                'trainingLocation' => 'EPROFOS - Salle informatique spécialisée - 123 Avenue de la Formation, 75001 Paris (postes équipés Office 365 + Power BI Pro)',
                'fundingModalities' => 'Éligible CPF (code 237682), financement OPCO, tarif dégressif entreprise, possibilité de paiement en 3 fois',
            ],

            // Management et Leadership
            [
                'title' => 'Leadership et Management d\'Équipe',
                'description' => 'Développez vos compétences de leader pour motiver, fédérer et faire grandir vos équipes. Formation pratique avec mises en situation.',
                'objectives' => "• Développer son style de leadership\n• Motiver et fédérer une équipe\n• Gérer les conflits et les résistances\n• Déléguer efficacement\n• Conduire le changement",
                'operationalObjectives' => [
                    'Identifier son style de management et l\'adapter selon les situations',
                    'Conduire des entretiens individuels structurés et constructifs',
                    'Animer des réunions d\'équipe efficaces et participatives',
                    'Déléguer des tâches avec suivi et accompagnement',
                    'Gérer les conflits par la médiation et la négociation',
                    'Accompagner le changement et gérer les résistances'
                ],
                'evaluableObjectives' => [
                    'Améliorer de 20% l\'engagement de son équipe en 3 mois',
                    'Réduire de 50% le nombre de conflits non résolus',
                    'Augmenter de 25% l\'autonomie des collaborateurs',
                    'Diminuer le taux d\'absentéisme de 15%',
                    'Atteindre 90% de satisfaction lors des entretiens annuels'
                ],
                'evaluationCriteria' => [
                    'Évaluation 360° avec amélioration de 2 points minimum',
                    'Mise en situation managériale notée par jury d\'experts',
                    'Plan d\'action personnalisé avec objectifs SMART',
                    'Présentation d\'un cas de résolution de conflit',
                    'Autoévaluation des compétences avant/après formation'
                ],
                'successIndicators' => [
                    'Taux de satisfaction des participants > 4.6/5',
                    'Note moyenne évaluation 360° > 4.2/5',
                    'Taux d\'application des acquis > 80% à 3 mois',
                    'Progression moyenne des compétences > 30%',
                    'Recommandation de la formation > 90%'
                ],
                'prerequisites' => 'Expérience en encadrement d\'équipe ou projet de prise de responsabilités managériales.',
                'program' => "Module 1: Fondamentaux du leadership (7h)\n- Styles de leadership\n- Intelligence émotionnelle\n- Communication assertive\n\nModule 2: Management d'équipe (14h)\n- Motivation et engagement\n- Gestion des personnalités\n- Entretiens individuels\n- Feedback constructif\n\nModule 3: Gestion des conflits (7h)\n- Prévention des conflits\n- Médiation et négociation\n- Résolution collaborative\n\nModule 4: Conduite du changement (7h)\n- Accompagnement du changement\n- Gestion des résistances\n- Communication du changement",
                'durationHours' => 35,
                'price' => '2190.00',
                'level' => 'Intermédiaire',
                'format' => 'Présentiel',
                'isFeatured' => true,
                'image' => 'formation-leadership-management.png',
                'category' => CategoryFixtures::CATEGORY_MANAGEMENT,
                'targetAudience' => 'Managers, chefs d\'équipe, superviseurs, futurs managers, dirigeants de PME, responsables de service',
                'accessModalities' => 'Inscription en ligne avec entretien préalable obligatoire. Délai d\'accès : 3 semaines. Groupes de 6 à 10 participants maximum.',
                'handicapAccessibility' => 'Formation accessible PMR. Adaptation des supports et méthodes selon les besoins. Référent : handicap@eprofos.fr',
                'teachingMethods' => 'Formation interactive avec jeux de rôles, mises en situation managériales, coaching individuel, analyse de cas concrets, plan d\'action personnalisé',
                'evaluationMethods' => 'Autoévaluation 360°, mise en situation managériale filmée et débriefée, élaboration d\'un plan de développement personnel',
                'contactInfo' => 'Référent pédagogique : jean.dupont@eprofos.fr - Tél : 01.42.85.96.25 | Coach certifié disponible post-formation',
                'trainingLocation' => 'EPROFOS - Espace formation management - 123 Avenue de la Formation, 75001 Paris - Salles modulables pour travaux de groupe',
                'fundingModalities' => 'Éligible CPF (code 235896), financement OPCO, budget formation entreprise, tarif spécial dirigeants TPE/PME',
            ],
            [
                'title' => 'Gestion de Projet Agile - Scrum Master',
                'description' => 'Maîtrisez la méthodologie Scrum et devenez Scrum Master certifié. Pilotez vos projets avec agilité et efficacité.',
                'objectives' => "• Comprendre les principes de l'agilité\n• Maîtriser le framework Scrum\n• Animer les cérémonies Scrum\n• Faciliter la collaboration d'équipe\n• Préparer la certification Scrum Master",
                'prerequisites' => 'Expérience en gestion de projet recommandée.',
                'program' => "Module 1: Agilité et Scrum (14h)\n- Manifeste agile\n- Framework Scrum\n- Rôles et responsabilités\n- Artefacts Scrum\n\nModule 2: Cérémonies Scrum (7h)\n- Sprint Planning\n- Daily Scrum\n- Sprint Review\n- Rétrospective\n\nModule 3: Facilitation d'équipe (7h)\n- Techniques d'animation\n- Résolution de problèmes\n- Amélioration continue\n\nModule 4: Préparation certification (7h)\n- Examen blanc\n- Révisions\n- Conseils pratiques",
                'durationHours' => 35,
                'price' => '2490.00',
                'level' => 'Intermédiaire',
                'format' => 'Hybride',
                'isFeatured' => false,
                'image' => null, // Test case with no image
                'category' => CategoryFixtures::CATEGORY_MANAGEMENT,
                'targetAudience' => 'Chefs de projet, Product Owners, développeurs, consultants, responsables équipes agiles, futurs Scrum Masters',
                'accessModalities' => 'Inscription en ligne. Formation hybride : 2 jours en présentiel + 3 sessions de 2h30 en distanciel. Délai d\'accès : 4 semaines.',
                'handicapAccessibility' => 'Formation accessible aux personnes en situation de handicap. Outils collaboratifs adaptés. Contact : handicap@eprofos.fr',
                'teachingMethods' => 'Formation hybride interactive, serious games agiles, simulations de projets Scrum, ateliers collaboratifs, certification officielle incluse',
                'evaluationMethods' => 'Évaluation continue par mise en pratique, simulation complète de projet Scrum, examen de certification Scrum Master PSM I',
                'contactInfo' => 'Référent pédagogique : marie.leroy@eprofos.fr - Tél : 01.42.85.96.28 | Certified Scrum Trainer disponible',
                'trainingLocation' => 'Formation hybride : EPROFOS Paris (2 jours) + plateforme collaborative en ligne pour sessions distancielles',
                'fundingModalities' => 'Éligible CPF (code 236547), OPCO, financement entreprise, certification PSM I incluse dans le tarif',
            ],

            // Langues Étrangères
            [
                'title' => 'Anglais Professionnel - Business English',
                'description' => 'Perfectionnez votre anglais professionnel pour communiquer efficacement dans un contexte international. Préparation au TOEIC.',
                'objectives' => "• Améliorer la communication orale et écrite\n• Maîtriser le vocabulaire business\n• Conduire des réunions en anglais\n• Rédiger des emails professionnels\n• Préparer le TOEIC",
                'prerequisites' => 'Niveau B1 minimum en anglais.',
                'program' => "Module 1: Communication orale (14h)\n- Présentations professionnelles\n- Négociation en anglais\n- Participation aux réunions\n- Appels téléphoniques\n\nModule 2: Communication écrite (14h)\n- Emails professionnels\n- Rapports et comptes-rendus\n- Correspondance commerciale\n\nModule 3: Vocabulaire business (7h)\n- Terminologie financière\n- Marketing et vente\n- Ressources humaines\n- Gestion de projet\n\nModule 4: Préparation TOEIC (7h)\n- Structure de l'examen\n- Stratégies de réponse\n- Tests blancs",
                'durationHours' => 42,
                'price' => '1690.00',
                'level' => 'Intermédiaire',
                'format' => 'Hybride',
                'isFeatured' => false,
                'image' => 'formation-anglais-business.jpg',
                'category' => CategoryFixtures::CATEGORY_LANGUES,
                'targetAudience' => 'Cadres, commerciaux, assistants de direction, consultants, toute personne travaillant en contexte international',
                'accessModalities' => 'Test de niveau obligatoire avant inscription. Formation hybride personnalisée. Délai d\'accès : 2 semaines. Groupes de niveau homogène.',
                'handicapAccessibility' => 'Formation accessible aux personnes malentendantes (supports visuels renforcés). Adaptation possible. Contact : handicap@eprofos.fr',
                'teachingMethods' => 'Formation hybride avec formateur natif, jeux de rôles professionnels, simulations de réunions, e-learning personnalisé, immersion linguistique',
                'evaluationMethods' => 'Évaluation continue, simulations de situations professionnelles, passage du TOEIC blanc, certification TOEIC officielle en fin de formation',
                'contactInfo' => 'Référent pédagogique : sarah.johnson@eprofos.fr - Tél : 01.42.85.96.30 | Formateurs natifs anglophones certifiés',
                'trainingLocation' => 'Formation hybride : EPROFOS Paris + plateforme e-learning avec laboratoire de langues virtuel accessible 24h/24',
                'fundingModalities' => 'Éligible CPF (code 236593), financement OPCO, DIF, prise en charge spécifique export, passage TOEIC inclus',
            ],

            // Comptabilité et Finance
            [
                'title' => 'Comptabilité Générale et Analyse Financière',
                'description' => 'Formation complète en comptabilité générale et analyse financière pour comprendre et piloter la performance de votre entreprise.',
                'objectives' => "• Maîtriser les principes comptables\n• Établir les documents de synthèse\n• Analyser la performance financière\n• Utiliser les ratios financiers\n• Optimiser la gestion de trésorerie",
                'prerequisites' => 'Notions de base en gestion d\'entreprise.',
                'program' => "Module 1: Comptabilité générale (21h)\n- Plan comptable général\n- Écritures comptables\n- Grand livre et balance\n- Bilan et compte de résultat\n\nModule 2: Analyse financière (14h)\n- Ratios de rentabilité\n- Ratios de liquidité\n- Analyse de l'endettement\n- Tableau de financement\n\nModule 3: Gestion de trésorerie (7h)\n- Prévisions de trésorerie\n- Optimisation des flux\n- Négociation bancaire",
                'durationHours' => 42,
                'price' => '2290.00',
                'level' => 'Débutant',
                'format' => 'Présentiel',
                'isFeatured' => false,
                'image' => 'formation-comptabilite-finance.png',
                'category' => CategoryFixtures::CATEGORY_COMPTABILITE,
                'targetAudience' => 'Dirigeants de PME, créateurs d\'entreprise, assistants comptables, responsables administratifs et financiers',
                'accessModalities' => 'Inscription en ligne. Délai d\'accès : 3 semaines. Formation intensive sur 6 jours. Groupes limités à 12 participants.',
                'handicapAccessibility' => 'Locaux accessibles PMR. Supports en gros caractères disponibles. Adaptation pédagogique possible. Contact : handicap@eprofos.fr',
                'teachingMethods' => 'Formation présentielle avec cas pratiques d\'entreprises réelles, exercices sur logiciel comptable, simulations de situations financières',
                'evaluationMethods' => 'QCM de validation des connaissances, étude de cas pratique complète, présentation d\'analyse financière d\'entreprise',
                'contactInfo' => 'Référent pédagogique : pierre.martin@eprofos.fr - Tél : 01.42.85.96.35 | Expert-comptable formateur certifié',
                'trainingLocation' => 'EPROFOS - Salle de formation comptabilité - 123 Avenue de la Formation, 75001 Paris - Équipée logiciels comptables professionnels',
                'fundingModalities' => 'Éligible CPF (code 237845), financement OPCO, aide spécifique créateurs d\'entreprise, tarif préférentiel TPE/PME',
            ],

            // Marketing et Communication
            [
                'title' => 'Marketing Digital et Réseaux Sociaux',
                'description' => 'Maîtrisez les leviers du marketing digital pour développer votre présence en ligne et générer des leads qualifiés.',
                'objectives' => "• Élaborer une stratégie digitale\n• Optimiser le référencement SEO/SEA\n• Gérer les réseaux sociaux professionnels\n• Mesurer le ROI des actions marketing\n• Créer du contenu engageant",
                'prerequisites' => 'Connaissances de base en marketing et utilisation d\'internet.',
                'program' => "Module 1: Stratégie digitale (7h)\n- Audit digital\n- Définition des objectifs\n- Persona et parcours client\n- Plan d'actions\n\nModule 2: SEO et SEA (14h)\n- Optimisation SEO\n- Campagnes Google Ads\n- Analyse des performances\n\nModule 3: Réseaux sociaux (14h)\n- LinkedIn professionnel\n- Facebook et Instagram\n- Création de contenu\n- Community management\n\nModule 4: Analytics et ROI (7h)\n- Google Analytics\n- KPIs et tableaux de bord\n- Optimisation continue",
                'durationHours' => 42,
                'price' => '2190.00',
                'level' => 'Intermédiaire',
                'format' => 'Hybride',
                'isFeatured' => true,
                'image' => 'formation-marketing-digital.jpg',
                'category' => CategoryFixtures::CATEGORY_MARKETING,
                'targetAudience' => 'Responsables marketing, chargés de communication, entrepreneurs, commerciaux, community managers, consultants',
                'accessModalities' => 'Inscription en ligne. Formation hybride sur 6 semaines. Délai d\'accès : 2 semaines. Accès plateforme e-learning inclus.',
                'handicapAccessibility' => 'Formation accessible PMR. Outils digitaux adaptés aux déficients visuels. Support personnalisé. Contact : handicap@eprofos.fr',
                'teachingMethods' => 'Formation hybride interactive, ateliers pratiques sur outils digitaux, création de campagnes réelles, analyse de cas concrets',
                'evaluationMethods' => 'Projet de stratégie digitale complète, création et lancement de campagne, présentation des résultats et ROI',
                'contactInfo' => 'Référent pédagogique : julie.bernard@eprofos.fr - Tél : 01.42.85.96.40 | Expert digital certifié Google disponible',
                'trainingLocation' => 'Formation hybride : EPROFOS Paris + plateforme e-learning avec accès outils professionnels (Google Ads, Analytics, etc.)',
                'fundingModalities' => 'Éligible CPF (code 237123), financement OPCO, aide spécifique TPE/PME, tarif dégressif pour équipes',
            ],

            // Ressources Humaines
            [
                'title' => 'Recrutement et Gestion des Talents',
                'description' => 'Optimisez vos processus de recrutement et développez une stratégie de gestion des talents pour attirer et fidéliser les meilleurs profils.',
                'objectives' => "• Définir les besoins en recrutement\n• Sourcer et évaluer les candidats\n• Conduire des entretiens efficaces\n• Intégrer les nouveaux collaborateurs\n• Développer les talents internes",
                'prerequisites' => 'Expérience en ressources humaines ou management.',
                'program' => "Module 1: Stratégie de recrutement (7h)\n- Analyse des besoins\n- Définition de poste\n- Sourcing candidats\n- Marque employeur\n\nModule 2: Sélection et évaluation (14h)\n- Techniques d'entretien\n- Tests et assessments\n- Prise de références\n- Décision de recrutement\n\nModule 3: Intégration (7h)\n- Processus d'onboarding\n- Suivi période d'essai\n- Formation initiale\n\nModule 4: Gestion des talents (7h)\n- Identification des potentiels\n- Plans de développement\n- Gestion des carrières\n- Fidélisation",
                'durationHours' => 35,
                'price' => '2090.00',
                'level' => 'Intermédiaire',
                'format' => 'Présentiel',
                'isFeatured' => false,
                'image' => 'formation-recrutement-rh.png',
                'category' => CategoryFixtures::CATEGORY_RESSOURCES_HUMAINES,
                'targetAudience' => 'DRH, responsables RH, chargés de recrutement, managers, dirigeants de PME, consultants en recrutement',
                'accessModalities' => 'Inscription en ligne avec entretien préalable. Délai d\'accès : 3 semaines. Groupes de 8 à 12 participants maximum.',
                'handicapAccessibility' => 'Formation accessible PMR. Supports adaptés aux déficients visuels. Méthodes d\'évaluation alternatives. Contact : handicap@eprofos.fr',
                'teachingMethods' => 'Formation présentielle interactive, simulations d\'entretiens filmées, études de cas RH, ateliers de construction d\'outils de recrutement',
                'evaluationMethods' => 'Mise en situation de recrutement complète, construction d\'un processus de recrutement, présentation d\'une stratégie talents',
                'contactInfo' => 'Référent pédagogique : anne.dubois@eprofos.fr - Tél : 01.42.85.96.45 | Consultant RH senior disponible',
                'trainingLocation' => 'EPROFOS - Espace RH - 123 Avenue de la Formation, 75001 Paris - Salles équipées pour simulations d\'entretiens',
                'fundingModalities' => 'Éligible CPF (code 237456), financement OPCO, budget formation RH, tarif spécial cabinets de recrutement',
            ],

            // Qualité et Amélioration Continue
            [
                'title' => 'Lean Management et Amélioration Continue',
                'description' => 'Implémentez les méthodes Lean pour optimiser vos processus, réduire les gaspillages et améliorer la performance opérationnelle.',
                'objectives' => "• Comprendre les principes du Lean\n• Identifier et éliminer les gaspillages\n• Mettre en place des outils Lean\n• Animer des chantiers d'amélioration\n• Mesurer les gains obtenus",
                'prerequisites' => 'Expérience en production ou amélioration des processus.',
                'program' => "Module 1: Fondamentaux Lean (7h)\n- Philosophie Lean\n- Les 8 gaspillages\n- Value Stream Mapping\n- Gemba Walk\n\nModule 2: Outils Lean (14h)\n- 5S et organisation\n- Kanban et flux tiré\n- SMED et changements rapides\n- Poka-Yoke\n\nModule 3: Animation d'équipe (7h)\n- Kaizen et amélioration continue\n- Animation de chantiers\n- Résolution de problèmes\n\nModule 4: Mesure et suivi (7h)\n- Indicateurs de performance\n- Tableaux de bord visuels\n- Pérennisation des gains",
                'durationHours' => 35,
                'price' => '2390.00',
                'level' => 'Intermédiaire',
                'format' => 'Présentiel',
                'isFeatured' => false,
                'image' => null, // Another test case with no image
                'category' => CategoryFixtures::CATEGORY_QUALITE,
                'targetAudience' => 'Responsables production, ingénieurs qualité, managers opérationnels, consultants en amélioration continue, chefs d\'équipe',
                'accessModalities' => 'Inscription en ligne. Délai d\'accès : 4 semaines. Formation intensive sur 5 jours. Visite d\'entreprise Lean incluse.',
                'handicapAccessibility' => 'Formation accessible PMR. Adaptation des ateliers pratiques selon les besoins. Support personnalisé. Contact : handicap@eprofos.fr',
                'teachingMethods' => 'Formation présentielle avec ateliers pratiques, simulations de production, visite d\'entreprise Lean, projets d\'amélioration en équipe',
                'evaluationMethods' => 'Projet d\'amélioration continue complet, présentation des gains obtenus, certification interne Lean EPROFOS',
                'contactInfo' => 'Référent pédagogique : michel.bernard@eprofos.fr - Tél : 01.42.85.96.50 | Expert Lean certifié disponible',
                'trainingLocation' => 'EPROFOS - Atelier Lean - 123 Avenue de la Formation, 75001 Paris + visite entreprise partenaire Lean Manufacturing',
                'fundingModalities' => 'Éligible CPF (code 237789), financement OPCO, aide spécifique industrie, tarif dégressif pour équipes complètes',
            ],
        ];

        foreach ($formations as $formationData) {
            $formation = new Formation();
            $formation->setTitle($formationData['title']);
            $formation->setSlug($this->slugger->slug($formationData['title'])->lower());
            $formation->setDescription($formationData['description']);
            $formation->setObjectives($formationData['objectives']);
            $formation->setPrerequisites($formationData['prerequisites']);
            // Note: program is now auto-generated from modules and chapters
            $formation->setDurationHours($formationData['durationHours']);
            $formation->setPrice($formationData['price']);
            $formation->setLevel($formationData['level']);
            $formation->setFormat($formationData['format']);
            $formation->setIsFeatured($formationData['isFeatured']);
            $formation->setIsActive(true);
            
            // Set image if provided
            if (isset($formationData['image'])) {
                $formation->setImage($formationData['image']);
            }
            
            // Set Qualiopi required properties
            $formation->setTargetAudience($formationData['targetAudience']);
            $formation->setAccessModalities($formationData['accessModalities']);
            $formation->setHandicapAccessibility($formationData['handicapAccessibility']);
            $formation->setTeachingMethods($formationData['teachingMethods']);
            $formation->setEvaluationMethods($formationData['evaluationMethods']);
            $formation->setContactInfo($formationData['contactInfo']);
            $formation->setTrainingLocation($formationData['trainingLocation']);
            $formation->setFundingModalities($formationData['fundingModalities']);
            
            // Set structured objectives for Qualiopi 2.5 compliance
            if (isset($formationData['operationalObjectives'])) {
                $formation->setOperationalObjectives($formationData['operationalObjectives']);
            }
            if (isset($formationData['evaluableObjectives'])) {
                $formation->setEvaluableObjectives($formationData['evaluableObjectives']);
            }
            if (isset($formationData['evaluationCriteria'])) {
                $formation->setEvaluationCriteria($formationData['evaluationCriteria']);
            }
            if (isset($formationData['successIndicators'])) {
                $formation->setSuccessIndicators($formationData['successIndicators']);
            }
            
            // Set category reference
            $category = $this->getReference($formationData['category'], Category::class);
            $formation->setCategory($category);

            $manager->persist($formation);
        }

        $manager->flush();
    }

    /**
     * Define fixture dependencies
     */
    public function getDependencies(): array
    {
        return [
            CategoryFixtures::class,
        ];
    }
}