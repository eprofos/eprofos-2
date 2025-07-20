<?php

namespace App\DataFixtures;

use App\Entity\Training\Chapter;
use App\Entity\Training\Module;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Faker\Factory;

class ChapterFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // Get all modules
        $modules = $manager->getRepository(Module::class)->findAll();

        if (empty($modules)) {
            return;
        }

        foreach ($modules as $module) {
            $chapterCount = $faker->numberBetween(3, 8);
            
            for ($i = 1; $i <= $chapterCount; $i++) {
                $chapter = new Chapter();
                
                // Generate realistic chapter titles based on module context
                $chapterTitles = $this->getChapterTitles($module->getTitle(), $i);
                $title = $faker->randomElement($chapterTitles);
                
                $chapter->setTitle($title);
                $chapter->setSlug($this->generateSlug($title, $module->getId(), $i));
                $chapter->setDescription($this->generateRealisticDescription($title));
                
                // Content outline (required by Qualiopi)
                $contentOutline = $this->generateContentOutline($faker, $title);
                $chapter->setContentOutline($contentOutline);
                
                // Learning objectives (required by Qualiopi)
                $learningObjectives = [];
                for ($j = 0; $j < $faker->numberBetween(2, 4); $j++) {
                    $learningObjectives[] = $this->generateLearningObjective($faker, $title);
                }
                $chapter->setLearningObjectives($learningObjectives);
                
                // Learning outcomes (required by Qualiopi)
                $learningOutcomes = [];
                for ($k = 0; $k < $faker->numberBetween(2, 5); $k++) {
                    $learningOutcomes[] = $this->generateLearningOutcome($faker, $title);
                }
                $chapter->setLearningOutcomes($learningOutcomes);
                
                // Prerequisites
                $chapter->setPrerequisites($faker->optional(0.6)->realText(150));
                
                // Teaching methods (required by Qualiopi)
                $teachingMethods = $faker->optional(0.8)->randomElement([
                    'Présentation interactive avec support visuel',
                    'Démonstration pratique étape par étape',
                    'Atelier participatif avec exercices dirigés',
                    'Étude de cas concrets et analyse collective',
                    'Travail en groupes et mise en commun',
                    'Jeu de rôle et simulation',
                    'Brainstorming et mind mapping',
                    'Questionnement socratique et débat',
                    'Apprentissage par la découverte'
                ]);
                $chapter->setTeachingMethods($teachingMethods);
                
                // Assessment methods (required by Qualiopi)
                $assessmentMethods = $faker->optional(0.8)->randomElement([
                    'QCM de vérification des acquis',
                    'Exercices pratiques avec correction immédiate',
                    'Auto-évaluation avec grille de compétences',
                    'Évaluation par les pairs',
                    'Questions-réponses interactives',
                    'Mise en situation et observation',
                    'Production d\'un livrable évaluable',
                    'Réflexion écrite et synthèse personnelle'
                ]);
                $chapter->setAssessmentMethods($assessmentMethods);
                
                // Resources
                $resources = [];
                $resourceTypes = [
                    'Support de cours interactif',
                    'Vidéo tutorielle',
                    'Fiche récapitulative',
                    'Exercices d\'application',
                    'Cas pratiques',
                    'Outils numériques',
                    'Documentation complémentaire',
                    'Références bibliographiques'
                ];
                for ($l = 0; $l < $faker->numberBetween(2, 5); $l++) {
                    $resources[] = $faker->randomElement($resourceTypes);
                }
                $chapter->setResources($resources);
                
                // Success criteria
                $successCriteria = [];
                for ($m = 0; $m < $faker->numberBetween(2, 4); $m++) {
                    $successCriteria[] = $this->generateSuccessCriteria($faker, $title);
                }
                $chapter->setSuccessCriteria($successCriteria);
                
                // Duration in minutes
                $chapter->setDurationMinutes($faker->numberBetween(30, 120));
                
                // Order index
                $chapter->setOrderIndex($i);
                
                $chapter->setModule($module);
                $chapter->setIsActive($faker->boolean(95)); // 95% chance of being active
                
                $manager->persist($chapter);
                
                // Add reference for other fixtures
                $this->addReference('chapter_' . $module->getId() . '_' . $i, $chapter);
            }
        }

        $manager->flush();
    }

    private function getChapterTitles(string $moduleTitle, int $chapterNumber): array
    {
        // Specific chapters based on module content
        if (stripos($moduleTitle, 'PHP') !== false && stripos($moduleTitle, 'Fondamentaux') !== false) {
            return [
                'Installation et configuration de l\'environnement PHP',
                'Syntaxe de base et variables',
                'Structures de contrôle et boucles',
                'Fonctions et portée des variables',
                'Gestion des erreurs et debugging'
            ];
        }

        if (stripos($moduleTitle, 'Symfony') !== false) {
            return [
                'Architecture MVC et composants Symfony',
                'Routing et contrôleurs',
                'Twig et gestion des vues',
                'Doctrine ORM et base de données',
                'Formulaires et validation'
            ];
        }

        if (stripos($moduleTitle, 'Excel') !== false && stripos($moduleTitle, 'avancées') !== false) {
            return [
                'Fonctions de recherche et référence',
                'Fonctions logiques et conditionnelles',
                'Fonctions de texte et manipulation de données',
                'Fonctions de date et heure',
                'Fonctions mathématiques avancées'
            ];
        }

        if (stripos($moduleTitle, 'Power BI') !== false) {
            return [
                'Interface et navigation Power BI',
                'Connexion aux sources de données',
                'Modélisation des données',
                'Création de visualisations',
                'Publication et partage de rapports'
            ];
        }

        if (stripos($moduleTitle, 'Leadership') !== false) {
            return [
                'Styles de leadership et adaptation',
                'Vision et communication inspirante',
                'Développement de l\'intelligence émotionnelle',
                'Gestion du changement et influence',
                'Coaching et développement des équipes'
            ];
        }

        if (stripos($moduleTitle, 'Marketing') !== false && stripos($moduleTitle, 'Digital') !== false) {
            return [
                'Écosystème du marketing digital',
                'Stratégie de contenu et storytelling',
                'SEO et référencement naturel',
                'Publicité payante (SEA, Social Ads)',
                'Marketing automation et lead nurturing'
            ];
        }

        if (stripos($moduleTitle, 'Comptabilité') !== false) {
            return [
                'Principes comptables fondamentaux',
                'Journal et grand livre',
                'Immobilisations et amortissements',
                'Stocks et provisions',
                'Rapprochement bancaire'
            ];
        }

        if (stripos($moduleTitle, 'Anglais') !== false) {
            return [
                'Vocabulaire professionnel essentiel',
                'Grammaire appliquée au business',
                'Techniques de conversation',
                'Rédaction de e-mails professionnels',
                'Présentations et prises de parole'
            ];
        }

        if (stripos($moduleTitle, 'Scrum') !== false) {
            return [
                'Rôles Scrum (Product Owner, Scrum Master)',
                'Événements Scrum (Sprint, Daily, Review)',
                'Artefacts Scrum (Backlog, Increment)',
                'Estimation et planification',
                'Rétrospectives et amélioration'
            ];
        }

        if (stripos($moduleTitle, 'Lean') !== false) {
            return [
                'Valeur ajoutée vs gaspillages',
                'Cartographie des flux de valeur',
                'Outils Lean (5S, Kanban, Poka-yoke)',
                'Amélioration continue (Kaizen)',
                'Mesure et indicateurs de performance'
            ];
        }

        if (stripos($moduleTitle, 'Recrutement') !== false) {
            return [
                'Définition du poste et profil candidat',
                'Sourcing et recherche de candidats',
                'Entretien structuré et évaluation',
                'Tests et mises en situation',
                'Prise de référence et décision'
            ];
        }

        if (stripos($moduleTitle, 'Cybersécurité') !== false) {
            return [
                'Typologie des menaces informatiques',
                'Analyse des vulnérabilités',
                'Mise en place de pare-feu',
                'Gestion des accès et authentification',
                'Plan de reprise d\'activité'
            ];
        }

        // Default generic chapters
        return [
            'Introduction et contexte',
            'Concepts fondamentaux',
            'Méthodologie et approches',
            'Outils et techniques',
            'Mise en pratique',
            'Cas d\'étude concrets',
            'Bonnes pratiques',
            'Évaluation et amélioration'
        ];
    }

    private function generateSlug(string $title, int $moduleId, int $chapterIndex): string
    {
        $slug = strtolower($title);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/\s+/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Add module ID and chapter index to ensure uniqueness
        $slug = "module-{$moduleId}-chapter-{$chapterIndex}-{$slug}";
        
        return $slug;
    }

    private function generateContentOutline($faker, string $title): string
    {
        $outlinePoints = [
            '1. Introduction au sujet',
            '2. Concepts clés et définitions',
            '3. Méthodologie d\'approche',
            '4. Exemples pratiques',
            '5. Exercices d\'application',
            '6. Points de vigilance',
            '7. Synthèse et takeaways'
        ];

        // Add context-specific points
        if (stripos($title, 'pratique') !== false) {
            $outlinePoints[] = '8. Ateliers hands-on';
            $outlinePoints[] = '9. Mise en situation';
        }

        if (stripos($title, 'évaluation') !== false) {
            $outlinePoints[] = '8. Grilles d\'évaluation';
            $outlinePoints[] = '9. Critères de réussite';
        }

        return implode("\n", $faker->randomElements($outlinePoints, $faker->numberBetween(5, 7)));
    }

    private function generateLearningObjective($faker, string $title): string
    {
        $objectiveStarters = [
            'Comprendre',
            'Maîtriser',
            'Appliquer',
            'Analyser',
            'Identifier',
            'Utiliser',
            'Développer',
            'Créer',
            'Évaluer'
        ];

        $objectiveContexts = [
            'les principes fondamentaux',
            'les techniques essentielles',
            'les outils appropriés',
            'les bonnes pratiques',
            'les méthodes d\'analyse',
            'les stratégies efficaces',
            'les indicateurs clés',
            'les processus d\'amélioration'
        ];

        $starter = $faker->randomElement($objectiveStarters);
        $context = $faker->randomElement($objectiveContexts);

        return $starter . ' ' . $context . ' du chapitre';
    }

    private function generateLearningOutcome($faker, string $title): string
    {
        $outcomeStarters = [
            'Être capable de',
            'Savoir',
            'Pouvoir',
            'Maîtriser',
            'Démontrer',
            'Appliquer',
            'Utiliser efficacement',
            'Analyser correctement'
        ];

        $outcomeActions = [
            'résoudre les problèmes courants',
            'implémenter les solutions appropriées',
            'évaluer les résultats obtenus',
            'optimiser les processus',
            'communiquer efficacement',
            'prendre des décisions éclairées',
            'gérer les situations complexes',
            'collaborer avec les équipes'
        ];

        $starter = $faker->randomElement($outcomeStarters);
        $action = $faker->randomElement($outcomeActions);

        return $starter . ' ' . $action;
    }

    private function generateSuccessCriteria($faker, string $title): string
    {
        $criteriaTemplates = [
            'Réussir {score}% des exercices du chapitre',
            'Démontrer la compréhension des {nb} concepts clés',
            'Appliquer correctement la méthodologie présentée',
            'Répondre correctement aux questions de validation',
            'Produire un livrable conforme aux attentes',
            'Participer activement aux discussions',
            'Compléter tous les exercices pratiques'
        ];

        $template = $faker->randomElement($criteriaTemplates);
        $score = $faker->numberBetween(70, 90);
        $nb = $faker->numberBetween(3, 6);

        return str_replace(['{score}', '{nb}'], [$score, $nb], $template);
    }

    private function generateRealisticDescription(string $chapterTitle): string
    {
        $descriptions = [
            'Installation et configuration de l\'environnement PHP' => 'Installation de PHP, configuration du serveur web, et mise en place des outils de développement nécessaires.',
            'Syntaxe de base et variables' => 'Apprentissage de la syntaxe PHP, déclaration et manipulation des variables, types de données primitifs.',
            'Structures de contrôle et boucles' => 'Utilisation des conditions (if, switch) et des boucles (for, while, foreach) pour contrôler le flux du programme.',
            'Fonctions et portée des variables' => 'Création et utilisation des fonctions, gestion de la portée des variables (scope), et paramètres.',
            'Gestion des erreurs et debugging' => 'Techniques de débogage, gestion des erreurs et exceptions, et bonnes pratiques de développement.',
            
            'Architecture MVC et composants Symfony' => 'Comprendre l\'architecture Modèle-Vue-Contrôleur et les composants fondamentaux de Symfony.',
            'Routing et contrôleurs' => 'Configuration des routes, création de contrôleurs, et gestion des requêtes HTTP.',
            'Twig et gestion des vues' => 'Utilisation du moteur de template Twig pour créer des vues dynamiques et maintenables.',
            'Doctrine ORM et base de données' => 'Intégration de Doctrine ORM pour la gestion des données et des relations entre entités.',
            'Formulaires et validation' => 'Création et validation de formulaires avec le composant Form de Symfony.',
            
            'Fonctions de recherche et référence' => 'Maîtrise des fonctions RECHERCHEV, INDEX/EQUIV, et autres fonctions de recherche avancées.',
            'Fonctions logiques et conditionnelles' => 'Utilisation des fonctions SI, ET, OU, et création de formules conditionnelles complexes.',
            'Fonctions de texte et manipulation de données' => 'Manipulation de chaînes de caractères, concaténation, et extraction de données textuelles.',
            'Fonctions de date et heure' => 'Calculs avec les dates, formatage temporel, et fonctions de manipulation du temps.',
            'Fonctions mathématiques avancées' => 'Fonctions statistiques, mathématiques, et d\'analyse de données numériques.',
        ];

        return $descriptions[$chapterTitle] ?? 'Ce chapitre traite des aspects pratiques de ' . strtolower($chapterTitle) . ' avec des exemples concrets et des exercices d\'application.';
    }

    public function getDependencies(): array
    {
        return [
            ModuleFixtures::class,
        ];
    }
}
