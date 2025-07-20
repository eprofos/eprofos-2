<?php

namespace App\DataFixtures;

use App\Entity\Training\QCM;
use App\Entity\Training\Course;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class QCMFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        
        $courses = $manager->getRepository(Course::class)->findAll();
        
        if (empty($courses)) {
            return;
        }

        $qcmIndex = 0;
        
        foreach ($courses as $course) {
            $qcmsPerCourse = $faker->numberBetween(1, 2);
            
            for ($i = 0; $i < $qcmsPerCourse; $i++) {
                $qcm = new QCM();
                $qcm->setTitle('QCM - ' . $course->getTitle());
                $qcm->setSlug($faker->slug . '-' . $qcmIndex);
                $qcm->setDescription('Questionnaire d\'évaluation pour ' . $course->getTitle());
                $qcm->setInstructions('Répondez aux questions suivantes en sélectionnant la ou les bonnes réponses.');
                
                // Generate questions
                $questions = [];
                $questionCount = $faker->numberBetween(5, 15);
                
                for ($j = 0; $j < $questionCount; $j++) {
                    $question = [
                        'question' => $faker->sentence . ' ?',
                        'type' => $faker->randomElement(['single', 'multiple']),
                        'answers' => [],
                        'correct_answers' => [],
                        'explanation' => $faker->sentence,
                        'points' => $faker->numberBetween(1, 5),
                    ];
                    
                    $answerCount = $faker->numberBetween(3, 6);
                    for ($k = 0; $k < $answerCount; $k++) {
                        $question['answers'][] = $faker->sentence;
                    }
                    
                    if ($question['type'] === 'single') {
                        $question['correct_answers'] = [$faker->numberBetween(0, $answerCount - 1)];
                    } else {
                        $correctCount = $faker->numberBetween(1, min(3, $answerCount));
                        $question['correct_answers'] = $faker->randomElements(
                            range(0, $answerCount - 1),
                            $correctCount
                        );
                    }
                    
                    $questions[] = $question;
                }
                
                $qcm->setQuestions($questions);
                
                $totalPoints = array_sum(array_column($questions, 'points'));
                $qcm->setMaxScore($totalPoints);
                $qcm->setPassingScore((int) ($totalPoints * 0.6)); // 60% pour réussir
                
                $qcm->setTimeLimitMinutes($faker->numberBetween(15, 60));
                $qcm->setMaxAttempts($faker->numberBetween(1, 3));
                $qcm->setShowCorrectAnswers(true);
                $qcm->setShowExplanations(true);
                $qcm->setRandomizeQuestions($faker->boolean);
                $qcm->setRandomizeAnswers($faker->boolean);
                $qcm->setOrderIndex($i + 1);
                $qcm->setCourse($course);
                
                // Set Qualiopi-compliant fields
                $qcm->setEvaluationCriteria([
                    'Exactitude des réponses sélectionnées',
                    'Compréhension des concepts évalués',
                    'Capacité d\'analyse et de synthèse',
                    'Respect du temps imparti',
                ]);
                
                $qcm->setSuccessCriteria([
                    'Obtention du score minimum requis',
                    'Validation de la compréhension des objectifs',
                    'Démonstration de l\'acquisition des connaissances',
                    'Capacité à appliquer les concepts dans des situations variées',
                ]);
                
                $manager->persist($qcm);
                $qcmIndex++;
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
