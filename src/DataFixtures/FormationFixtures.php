<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Formation;
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
                'prerequisites' => 'Connaissances de base en programmation et HTML/CSS. Expérience préalable en PHP recommandée.',
                'program' => "Module 1: Fondamentaux PHP 8.3 (14h)\n- Nouveautés PHP 8.3\n- Programmation orientée objet avancée\n- Gestion des erreurs et exceptions\n\nModule 2: Framework Symfony 7 (21h)\n- Architecture MVC\n- Routing et contrôleurs\n- Templates Twig\n- Formulaires et validation\n\nModule 3: Base de données et Doctrine (14h)\n- ORM Doctrine\n- Migrations et fixtures\n- Relations entre entités\n\nModule 4: Sécurité et API (14h)\n- Authentification et autorisation\n- Développement d'API REST\n- Tests unitaires et fonctionnels",
                'durationHours' => 63,
                'price' => '2890.00',
                'level' => 'Intermédiaire',
                'format' => 'Présentiel',
                'isFeatured' => true,
                'image' => 'formation-php-symfony.jpg',
                'category' => CategoryFixtures::CATEGORY_INFORMATIQUE,
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
            ],

            // Management et Leadership
            [
                'title' => 'Leadership et Management d\'Équipe',
                'description' => 'Développez vos compétences de leader pour motiver, fédérer et faire grandir vos équipes. Formation pratique avec mises en situation.',
                'objectives' => "• Développer son style de leadership\n• Motiver et fédérer une équipe\n• Gérer les conflits et les résistances\n• Déléguer efficacement\n• Conduire le changement",
                'prerequisites' => 'Expérience en encadrement d\'équipe ou projet de prise de responsabilités managériales.',
                'program' => "Module 1: Fondamentaux du leadership (7h)\n- Styles de leadership\n- Intelligence émotionnelle\n- Communication assertive\n\nModule 2: Management d'équipe (14h)\n- Motivation et engagement\n- Gestion des personnalités\n- Entretiens individuels\n- Feedback constructif\n\nModule 3: Gestion des conflits (7h)\n- Prévention des conflits\n- Médiation et négociation\n- Résolution collaborative\n\nModule 4: Conduite du changement (7h)\n- Accompagnement du changement\n- Gestion des résistances\n- Communication du changement",
                'durationHours' => 35,
                'price' => '2190.00',
                'level' => 'Intermédiaire',
                'format' => 'Présentiel',
                'isFeatured' => true,
                'image' => 'formation-leadership-management.png',
                'category' => CategoryFixtures::CATEGORY_MANAGEMENT,
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
            ],
        ];

        foreach ($formations as $formationData) {
            $formation = new Formation();
            $formation->setTitle($formationData['title']);
            $formation->setSlug($this->slugger->slug($formationData['title'])->lower());
            $formation->setDescription($formationData['description']);
            $formation->setObjectives($formationData['objectives']);
            $formation->setPrerequisites($formationData['prerequisites']);
            $formation->setProgram($formationData['program']);
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