<?php

namespace App\DataFixtures;

use App\Entity\Chapter;
use App\Entity\Module;
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
                $chapter->setDescription($faker->realText(250));
                
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
        $baseChapters = [
            'Introduction et contexte',
            'Concepts fondamentaux',
            'Méthodologie et approches',
            'Outils et techniques',
            'Mise en pratique',
            'Cas d\'étude',
            'Bonnes pratiques',
            'Évaluation et amélioration',
            'Synthèse et perspectives'
        ];

        // Context-specific chapters based on module title
        if (stripos($moduleTitle, 'digital') !== false || stripos($moduleTitle, 'numérique') !== false) {
            return [
                'Écosystème numérique',
                'Outils digitaux essentiels',
                'Stratégies numériques',
                'Transformation digitale',
                'Mesure de performance',
                'Optimisation continue',
                'Tendances et innovations',
                'Sécurité numérique'
            ];
        }

        if (stripos($moduleTitle, 'gestion') !== false || stripos($moduleTitle, 'management') !== false) {
            return [
                'Principes de management',
                'Leadership et autorité',
                'Gestion des équipes',
                'Communication managériale',
                'Prise de décision',
                'Gestion des conflits',
                'Motivation et engagement',
                'Évaluation des performances'
            ];
        }

        if (stripos($moduleTitle, 'communication') !== false) {
            return [
                'Bases de la communication',
                'Communication verbale',
                'Communication non-verbale',
                'Écoute active',
                'Techniques de présentation',
                'Communication écrite',
                'Gestion des objections',
                'Communication de crise'
            ];
        }

        if (stripos($moduleTitle, 'vente') !== false || stripos($moduleTitle, 'commercial') !== false) {
            return [
                'Prospection efficace',
                'Qualification des besoins',
                'Présentation de l\'offre',
                'Traitement des objections',
                'Techniques de closing',
                'Négociation commerciale',
                'Suivi client',
                'Fidélisation'
            ];
        }

        return $baseChapters;
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

    public function getDependencies(): array
    {
        return [
            ModuleFixtures::class,
        ];
    }
}
