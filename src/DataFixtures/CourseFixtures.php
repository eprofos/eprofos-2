<?php

namespace App\DataFixtures;

use App\Entity\Training\Course;
use App\Entity\Training\Chapter;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class CourseFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        
        $chapters = $manager->getRepository(Chapter::class)->findAll();
        
        if (empty($chapters)) {
            return;
        }

        $courseTypes = [
            Course::TYPE_LESSON,
            Course::TYPE_VIDEO,
            Course::TYPE_DOCUMENT,
            Course::TYPE_INTERACTIVE,
            Course::TYPE_PRACTICAL,
        ];

        $courseContents = [
            'lesson' => [
                'Cours introductif sur les concepts fondamentaux',
                'Présentation théorique des principes de base',
                'Analyse des bonnes pratiques',
                'Étude de cas concrets',
                'Synthèse et conclusions',
            ],
            'video' => [
                'Démonstration pratique en vidéo',
                'Tutoriel pas à pas',
                'Présentation interactive',
                'Exemple d\'application',
                'Récapitulatif visuel',
            ],
            'document' => [
                'Documentation technique complète',
                'Guide de référence',
                'Manuel d\'utilisation',
                'Ressources complémentaires',
                'Bibliographie',
            ],
            'interactive' => [
                'Atelier pratique interactif',
                'Simulation d\'environnement',
                'Exercice guidé',
                'Jeu pédagogique',
                'Quiz interactif',
            ],
            'practical' => [
                'Travaux pratiques dirigés',
                'Mise en situation réelle',
                'Projet d\'application',
                'Laboratoire d\'expérimentation',
                'Atelier de production',
            ],
        ];

        $courseIndex = 0;
        
        foreach ($chapters as $chapter) {
            $coursesPerChapter = $faker->numberBetween(2, 5);
            
            for ($i = 0; $i < $coursesPerChapter; $i++) {
                $type = $faker->randomElement($courseTypes);
                $contents = $courseContents[$type];
                
                $course = new Course();
                $course->setTitle($faker->randomElement($contents));
                $course->setSlug($faker->slug . '-' . $courseIndex);
                $course->setDescription($faker->sentences(3, true));
                $course->setType($type);
                $course->setContent($faker->paragraphs(3, true));
                $course->setDurationMinutes($faker->numberBetween(15, 120));
                $course->setOrderIndex($i + 1);
                $course->setChapter($chapter);
                
                // Set Qualiopi-compliant fields
                $course->setLearningObjectives([
                    'Maîtriser les concepts fondamentaux de ' . strtolower($course->getTitle()),
                    'Appliquer les techniques présentées dans des cas concrets',
                    'Analyser et résoudre des problèmes pratiques',
                ]);
                
                $course->setContentOutline($faker->paragraphs(2, true));
                $course->setPrerequisites($faker->sentence);
                
                $course->setLearningOutcomes([
                    'Connaissance approfondie des concepts',
                    'Capacité d\'application pratique',
                    'Autonomie dans la résolution de problèmes',
                ]);
                
                $course->setTeachingMethods($faker->randomElement([
                    'Cours magistral avec support visuel',
                    'Démonstration pratique et exercices',
                    'Apprentissage par projet',
                    'Méthode participative et interactive',
                    'Étude de cas et analyse critique',
                ]));
                
                $course->setResources([
                    'Support de cours PDF',
                    'Ressources documentaires',
                    'Outils logiciels',
                    'Accès à la plateforme en ligne',
                ]);
                
                $course->setAssessmentMethods($faker->randomElement([
                    'Évaluation formative par quiz',
                    'Contrôle continu des exercices',
                    'Projet d\'application pratique',
                    'Présentation orale',
                    'Rapport d\'analyse',
                ]));
                
                $course->setSuccessCriteria([
                    'Réussite des exercices pratiques',
                    'Validation des acquis par évaluation',
                    'Participation active aux activités',
                    'Qualité des livrables produits',
                ]);
                
                $manager->persist($course);
                $courseIndex++;
            }
        }
        
        $manager->flush();
    }
    
    public function getDependencies(): array
    {
        return [
            ChapterFixtures::class,
        ];
    }
}
