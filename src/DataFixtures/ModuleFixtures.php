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
                $module->setDescription($faker->realText(300));
                
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
        $baseModules = [
            'Introduction et fondamentaux',
            'Concepts avancés',
            'Mise en pratique',
            'Cas d\'usage professionnels',
            'Synthèse et évaluation'
        ];

        // Context-specific modules based on formation title
        if (stripos($formationTitle, 'digital') !== false || stripos($formationTitle, 'numérique') !== false) {
            return [
                'Fondamentaux du digital',
                'Transformation numérique',
                'Outils digitaux avancés',
                'Stratégie digitale',
                'Mesure et optimisation'
            ];
        }

        if (stripos($formationTitle, 'gestion') !== false || stripos($formationTitle, 'management') !== false) {
            return [
                'Principes de base du management',
                'Gestion d\'équipe',
                'Communication managériale',
                'Prise de décision',
                'Évaluation des performances'
            ];
        }

        if (stripos($formationTitle, 'communication') !== false) {
            return [
                'Fondamentaux de la communication',
                'Communication interpersonnelle',
                'Communication de groupe',
                'Communication digitale',
                'Évaluation de l\'impact'
            ];
        }

        if (stripos($formationTitle, 'vente') !== false || stripos($formationTitle, 'commercial') !== false) {
            return [
                'Techniques de vente',
                'Prospection et qualification',
                'Négociation commerciale',
                'Suivi client',
                'Analyse des résultats'
            ];
        }

        return $baseModules;
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

    public function getDependencies(): array
    {
        return [
            FormationFixtures::class,
        ];
    }
}
