<?php

namespace App\DataFixtures;

use App\Entity\Training\Exercise;
use App\Entity\Training\Course;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class ExerciseFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        
        $courses = $manager->getRepository(Course::class)->findAll();
        
        if (empty($courses)) {
            return;
        }

        $exerciseTypes = [
            Exercise::TYPE_INDIVIDUAL,
            Exercise::TYPE_GROUP,
            Exercise::TYPE_PRACTICAL,
            Exercise::TYPE_THEORETICAL,
            Exercise::TYPE_CASE_STUDY,
            Exercise::TYPE_SIMULATION,
        ];

        $difficulties = [
            Exercise::DIFFICULTY_BEGINNER,
            Exercise::DIFFICULTY_INTERMEDIATE,
            Exercise::DIFFICULTY_ADVANCED,
            Exercise::DIFFICULTY_EXPERT,
        ];

        $exerciseTemplates = [
            'individual' => [
                'Analyse personnelle d\'un cas d\'étude',
                'Rédaction d\'un rapport individuel',
                'Résolution d\'un problème technique',
                'Création d\'un prototype',
                'Recherche documentaire approfondie',
            ],
            'group' => [
                'Projet collaboratif en équipe',
                'Débat et argumentation collective',
                'Présentation de groupe',
                'Atelier de brainstorming',
                'Simulation d\'équipe',
            ],
            'practical' => [
                'Manipulation d\'outils professionnels',
                'Réalisation d\'un travail concret',
                'Expérimentation guidée',
                'Atelier de production',
                'Mise en pratique immédiate',
            ],
            'theoretical' => [
                'Analyse conceptuelle approfondie',
                'Étude comparative de théories',
                'Dissertation argumentée',
                'Synthèse théorique',
                'Réflexion critique',
            ],
            'case_study' => [
                'Étude de cas réel d\'entreprise',
                'Analyse de situation complexe',
                'Résolution de problème concret',
                'Diagnostic et préconisations',
                'Évaluation de scénarios',
            ],
            'simulation' => [
                'Simulation d\'environnement professionnel',
                'Jeu de rôle interactif',
                'Mise en situation réelle',
                'Exercice de simulation',
                'Pratique en environnement virtuel',
            ],
        ];

        $exerciseIndex = 0;
        
        foreach ($courses as $course) {
            $exercisesPerCourse = $faker->numberBetween(1, 3);
            
            for ($i = 0; $i < $exercisesPerCourse; $i++) {
                $type = $faker->randomElement($exerciseTypes);
                $templates = $exerciseTemplates[$type];
                
                $exercise = new Exercise();
                $exercise->setTitle($faker->randomElement($templates));
                $exercise->setSlug($faker->slug . '-' . $exerciseIndex);
                $exercise->setDescription($faker->sentences(2, true));
                $exercise->setType($type);
                $exercise->setDifficulty($faker->randomElement($difficulties));
                $exercise->setEstimatedDurationMinutes($faker->numberBetween(30, 180));
                $exercise->setMaxPoints($faker->numberBetween(20, 100));
                $exercise->setPassingPoints($faker->numberBetween(10, 60));
                $exercise->setOrderIndex($i + 1);
                $exercise->setCourse($course);
                
                // Set detailed instructions
                $exercise->setInstructions($faker->paragraphs(3, true));
                
                // Set Qualiopi-compliant fields
                $exercise->setExpectedOutcomes([
                    'Réalisation complète de l\'exercice selon les consignes',
                    'Application correcte des concepts enseignés',
                    'Production d\'un livrable de qualité',
                    'Respect des délais et contraintes',
                ]);
                
                $exercise->setEvaluationCriteria([
                    'Exactitude de la réalisation',
                    'Qualité de l\'analyse',
                    'Respect des consignes',
                    'Originalité et créativité',
                    'Présentation et communication',
                ]);
                
                $exercise->setResources([
                    'Documentation technique',
                    'Outils et logiciels nécessaires',
                    'Exemples et modèles',
                    'Support pédagogique',
                    'Accès aux ressources en ligne',
                ]);
                
                $exercise->setPrerequisites($faker->sentence);
                
                $exercise->setSuccessCriteria([
                    'Obtention de la note minimale requise',
                    'Validation par l\'évaluateur',
                    'Respect des critères qualité',
                    'Démonstration de la maîtrise des concepts',
                ]);
                
                $manager->persist($exercise);
                $exerciseIndex++;
            }
        }
        
        $manager->flush();
    }
    
    public function getDependencies(): array
    {
        return [
            CourseFixtures::class,
        ];
    }
}
