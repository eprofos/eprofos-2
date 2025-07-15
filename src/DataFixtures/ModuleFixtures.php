<?php

namespace App\DataFixtures;

use App\Entity\Module;
use App\Entity\Formation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Faker\Factory;

class ModuleFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // Get all formations
        $formations = $manager->getRepository(Formation::class)->findAll();

        if (empty($formations)) {
            return;
        }

        foreach ($formations as $formation) {
            $moduleCount = $faker->numberBetween(2, 5);
            
            for ($i = 1; $i <= $moduleCount; $i++) {
                $module = new Module();
                
                // Generate realistic module titles based on formation context
                $moduleTitles = $this->getModuleTitles($formation->getTitle(), $i);
                $title = $faker->randomElement($moduleTitles);
                
                $module->setTitle($title);
                $module->setSlug($this->generateSlug($title, $formation->getId(), $i));
                $module->setDescription($this->generateRealisticDescription($title, $formation->getTitle()));
                
                // Learning objectives (required by Qualiopi)
                $learningObjectives = [];
                for ($j = 0; $j < $faker->numberBetween(3, 6); $j++) {
                    $learningObjectives[] = $this->generateLearningObjective($faker, $formation->getTitle());
                }
                $module->setLearningObjectives($learningObjectives);
                
                // Prerequisites
                $module->setPrerequisites($faker->optional(0.7)->realText(200));
                
                // Duration in hours
                $module->setDurationHours($faker->numberBetween(4, 16));
                
                // Order index
                $module->setOrderIndex($i);
                
                // Evaluation methods (required by Qualiopi)
                $evaluationMethods = $faker->optional(0.8)->randomElement([
                    'Évaluation formative par exercices pratiques et mises en situation',
                    'QCM de validation des acquis en fin de module',
                    'Projet pratique avec grille d\'évaluation',
                    'Évaluation par les pairs et auto-évaluation',
                    'Étude de cas et analyse critique',
                    'Présentation orale et soutenance',
                    'Portfolio de compétences développées'
                ]);
                $module->setEvaluationMethods($evaluationMethods);
                
                // Teaching methods (required by Qualiopi)
                $teachingMethods = $faker->optional(0.8)->randomElement([
                    'Cours magistral interactif avec support multimédia',
                    'Ateliers pratiques et travaux dirigés',
                    'Méthode participative et collaborative',
                    'Apprentissage par problèmes (APP)',
                    'Étude de cas réels et simulation',
                    'Pédagogie inversée (classe inversée)',
                    'Formation-action et mise en situation professionnelle'
                ]);
                $module->setTeachingMethods($teachingMethods);
                
                // Resources
                $resources = [];
                $resourceTypes = [
                    'Support de cours PDF',
                    'Vidéos explicatives',
                    'Exercices pratiques',
                    'Fiches mémo',
                    'Outils logiciels',
                    'Documentation technique',
                    'Bibliographie spécialisée'
                ];
                for ($k = 0; $k < $faker->numberBetween(2, 4); $k++) {
                    $resources[] = $faker->randomElement($resourceTypes);
                }
                $module->setResources($resources);
                
                // Success criteria
                $successCriteria = [];
                for ($l = 0; $l < $faker->numberBetween(2, 4); $l++) {
                    $successCriteria[] = $this->generateSuccessCriteria($faker, $formation->getTitle());
                }
                $module->setSuccessCriteria($successCriteria);
                
                $module->setFormation($formation);
                $module->setIsActive($faker->boolean(90)); // 90% chance of being active
                
                $manager->persist($module);
                
                // Add reference for other fixtures
                $this->addReference('module_' . $formation->getId() . '_' . $i, $module);
            }
        }

        $manager->flush();
    }

    private function getModuleTitles(string $formationTitle, int $moduleNumber): array
    {
        // Specific modules based on actual formation titles
        if (stripos($formationTitle, 'PHP') !== false && stripos($formationTitle, 'Symfony') !== false) {
            return [
                'Fondamentaux PHP et environnement de développement',
                'Programmation orientée objet avec PHP',
                'Introduction au framework Symfony',
                'Développement d\'applications web avec Symfony',
                'Déploiement et bonnes pratiques'
            ];
        }

        if (stripos($formationTitle, 'Excel') !== false && stripos($formationTitle, 'Power BI') !== false) {
            return [
                'Maîtrise des fonctions avancées Excel',
                'Tableaux croisés dynamiques et analyse de données',
                'Macros et automatisation VBA',
                'Introduction à Power BI',
                'Création de tableaux de bord interactifs'
            ];
        }

        if (stripos($formationTitle, 'Leadership') !== false && stripos($formationTitle, 'Management') !== false) {
            return [
                'Fondamentaux du leadership',
                'Gestion et motivation d\'équipe',
                'Communication managériale efficace',
                'Prise de décision et résolution de conflits',
                'Évaluation des performances et développement des talents'
            ];
        }

        if (stripos($formationTitle, 'Marketing Digital') !== false) {
            return [
                'Stratégie marketing digital',
                'Réseaux sociaux et community management',
                'Publicité en ligne et référencement',
                'Analytics et mesure de performance',
                'Marketing automation et CRM'
            ];
        }

        if (stripos($formationTitle, 'Comptabilité') !== false) {
            return [
                'Fondamentaux de la comptabilité générale',
                'Écritures comptables et grand livre',
                'Bilan et compte de résultat',
                'Analyse financière et ratios',
                'Fiscalité et déclarations'
            ];
        }

        if (stripos($formationTitle, 'Anglais') !== false) {
            return [
                'Anglais professionnel - niveau de base',
                'Communication business et présentations',
                'Négociation et réunions en anglais',
                'Correspondance professionnelle',
                'Anglais technique et spécialisé'
            ];
        }

        if (stripos($formationTitle, 'Gestion de Projet') !== false && stripos($formationTitle, 'Agile') !== false) {
            return [
                'Principes de la gestion de projet Agile',
                'Scrum framework et rôles',
                'Planification et estimation Agile',
                'Ceremonies et outils Scrum',
                'Coaching et amélioration continue'
            ];
        }

        if (stripos($formationTitle, 'Lean') !== false) {
            return [
                'Principes fondamentaux du Lean Management',
                'Identification et élimination des gaspillages',
                'Outils Lean et amélioration continue',
                'Mise en place du système Lean',
                'Leadership et culture Lean'
            ];
        }

        if (stripos($formationTitle, 'Recrutement') !== false) {
            return [
                'Stratégie de recrutement et sourcing',
                'Techniques d\'entretien et évaluation',
                'Processus de sélection et décision',
                'Intégration et suivi des nouveaux collaborateurs',
                'Marque employeur et fidélisation'
            ];
        }

        if (stripos($formationTitle, 'Cybersécurité') !== false) {
            return [
                'Fondamentaux de la cybersécurité',
                'Menaces et vulnérabilités informatiques',
                'Mise en place de politiques de sécurité',
                'Gestion des incidents et réponse aux menaces',
                'Conformité et réglementation RGPD'
            ];
        }

        // Default generic modules
        return [
            'Introduction et fondamentaux',
            'Concepts avancés et méthodologies',
            'Mise en pratique et cas d\'usage',
            'Perfectionnement et bonnes pratiques',
            'Synthèse et évaluation finale'
        ];
    }

    private function generateSlug(string $title, int $formationId, int $moduleIndex): string
    {
        $slug = strtolower($title);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/\s+/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Add formation ID and module index to ensure uniqueness
        $slug = "formation-{$formationId}-module-{$moduleIndex}-{$slug}";
        
        return $slug;
    }

    private function generateLearningObjective($faker, string $formationTitle): string
    {
        $objectiveStarters = [
            'Maîtriser',
            'Comprendre',
            'Analyser',
            'Appliquer',
            'Développer',
            'Identifier',
            'Utiliser',
            'Créer',
            'Évaluer',
            'Gérer'
        ];

        $objectiveContexts = [
            'les concepts fondamentaux',
            'les outils professionnels',
            'les techniques avancées',
            'les bonnes pratiques',
            'les méthodes d\'analyse',
            'les stratégies efficaces',
            'les processus d\'amélioration',
            'les indicateurs de performance'
        ];

        $starter = $faker->randomElement($objectiveStarters);
        $context = $faker->randomElement($objectiveContexts);

        return $starter . ' ' . $context . ' dans le contexte professionnel';
    }

    private function generateSuccessCriteria($faker, string $formationTitle): string
    {
        $criteriaTemplates = [
            'Réussir {score}% des exercices pratiques',
            'Obtenir une note minimale de {score}/20 à l\'évaluation',
            'Démontrer la maîtrise de {skill} concepts clés',
            'Produire un livrable conforme aux spécifications',
            'Présenter un projet validé par l\'évaluateur',
            'Réaliser une mise en situation professionnelle réussie'
        ];

        $template = $faker->randomElement($criteriaTemplates);
        $score = $faker->numberBetween(70, 90);
        $skill = $faker->numberBetween(3, 7);

        return str_replace(['{score}', '{skill}'], [$score, $skill], $template);
    }

    private function generateRealisticDescription(string $moduleTitle, string $formationTitle): string
    {
        $descriptions = [
            'Fondamentaux PHP et environnement de développement' => 'Ce module couvre l\'installation et la configuration de PHP, les bases de la syntaxe, les variables et types de données, ainsi que la mise en place d\'un environnement de développement professionnel.',
            'Programmation orientée objet avec PHP' => 'Découvrez les concepts de la programmation orientée objet en PHP : classes, objets, héritage, encapsulation, et polymorphisme. Mise en pratique avec des exemples concrets.',
            'Introduction au framework Symfony' => 'Introduction au framework Symfony : architecture MVC, composants, bundles, et première application. Compréhension des concepts fondamentaux pour développer avec Symfony.',
            'Développement d\'applications web avec Symfony' => 'Développement complet d\'applications web avec Symfony : routing, contrôleurs, vues Twig, formulaires, sécurité, et intégration de bases de données avec Doctrine.',
            'Déploiement et bonnes pratiques' => 'Techniques de déploiement, gestion des environnements, tests automatisés, et bonnes pratiques de développement. Optimisation des performances et sécurité.',
            
            'Maîtrise des fonctions avancées Excel' => 'Approfondissement des fonctions Excel : RECHERCHEV, INDEX/EQUIV, fonctions logiques complexes, et manipulation avancée des données.',
            'Tableaux croisés dynamiques et analyse de données' => 'Création et personnalisation de tableaux croisés dynamiques, analyse de grandes bases de données, et techniques de reporting avancées.',
            'Macros et automatisation VBA' => 'Initiation à VBA pour automatiser les tâches répétitives, création de macros personnalisées, et développement d\'applications Excel.',
            'Introduction à Power BI' => 'Découverte de Power BI : interface, connexions de données, modélisation, et création de premiers rapports interactifs.',
            'Création de tableaux de bord interactifs' => 'Conception de tableaux de bord professionnels avec Power BI : visualisations avancées, filtres interactifs, et publication.',
            
            'Fondamentaux du leadership' => 'Comprendre les différents styles de leadership, développer sa présence de leader, et identifier ses forces et axes d\'amélioration.',
            'Gestion et motivation d\'équipe' => 'Techniques de management d\'équipe, motivation des collaborateurs, délégation efficace, et gestion des performances.',
            'Communication managériale efficace' => 'Développer ses compétences en communication : écoute active, feedback constructif, réunions efficaces, et gestion des conflits.',
            'Prise de décision et résolution de conflits' => 'Méthodes de prise de décision, résolution de problèmes complexes, médiation et gestion des situations difficiles.',
            'Évaluation des performances et développement des talents' => 'Conduite d\'entretiens d\'évaluation, identification des talents, plans de développement, et accompagnement des collaborateurs.',
        ];

        return $descriptions[$moduleTitle] ?? 'Ce module aborde les aspects essentiels de ' . strtolower($moduleTitle) . ' dans le contexte de la formation ' . $formationTitle . '.';
    }

    public function getDependencies(): array
    {
        return [
            FormationFixtures::class,
        ];
    }
}
