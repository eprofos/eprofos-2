<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Alternance\AlternanceContract;
use App\Entity\Alternance\ProgressAssessment;
use App\Entity\Core\StudentProgress;
use App\Entity\User\Student;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

/**
 * ProgressAssessmentFixtures.
 *
 * Generates realistic progress assessment data for tracking student progression
 * across training center and company environments.
 */
class ProgressAssessmentFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        $students = $manager->getRepository(Student::class)->findAll();

        if (empty($students)) {
            echo "Warning: Students not found. Skipping ProgressAssessmentFixtures.\n";

            return;
        }

        // First, let's link existing StudentProgress records to AlternanceContracts
        $this->linkProgressToContracts($manager);

        $assessmentsCreated = 0;
        $maxAssessments = 100; // Limit for performance

        foreach ($students as $student) {
            if ($assessmentsCreated >= $maxAssessments) {
                break;
            }

            // Check if student has alternance contract
            $studentProgress = $manager->getRepository(StudentProgress::class)->findOneBy(['student' => $student]);
            if (!$studentProgress || !$studentProgress->getAlternanceContract()) {
                continue; // Skip students without alternance contracts
            }

            // Generate 3-6 progress assessments per student over time
            $numAssessments = $faker->numberBetween(3, 6);
            $periodDates = [];

            // Generate periodic assessment dates (monthly or bi-monthly)
            $startDate = $faker->dateTimeBetween('-10 months', '-6 months');
            for ($i = 0; $i < $numAssessments; $i++) {
                $periodDates[] = (clone $startDate)->modify("+{$i} months");
            }

            foreach ($periodDates as $index => $periodDate) {
                if ($assessmentsCreated >= $maxAssessments) {
                    break;
                }

                $assessment = new ProgressAssessment();
                $assessment->setStudent($student)
                    ->setPeriod(DateTimeImmutable::createFromMutable($periodDate))
                ;

                // Progressive development over time
                $baseProgression = 20 + ($index * 15); // Start at 20%, increase by 15% each period
                $randomVariation = $faker->numberBetween(-5, 10);

                $centerProgression = max(0, min(100, $baseProgression + $randomVariation));
                $companyProgression = max(0, min(100, $baseProgression + $faker->numberBetween(-10, 5)));

                $assessment->setCenterProgression(number_format($centerProgression, 2))
                    ->setCompanyProgression(number_format($companyProgression, 2))
                ;

                $assessment->calculateOverallProgression();

                // Generate completed objectives (increasing over time)
                $completedObjectives = [];
                $numCompleted = min(8, 2 + $index * 2); // Start with 2, add 2 each period

                $objectiveTemplates = [
                    'technique' => [
                        'Maîtriser les fondamentaux du langage de programmation',
                        'Développer une application web complète',
                        'Implémenter des tests unitaires',
                        'Optimiser les performances du code',
                        'Utiliser un système de gestion de version',
                        'Créer une API REST fonctionnelle',
                        'Intégrer une base de données',
                        'Déployer une application en production',
                    ],
                    'projet' => [
                        'Participer à la conception d\'un projet',
                        'Rédiger la documentation technique',
                        'Collaborer efficacement en équipe',
                        'Respecter les délais fixés',
                        'Présenter les résultats aux parties prenantes',
                        'Gérer les retours et corrections',
                        'Proposer des améliorations',
                        'Mentor un autre alternant',
                    ],
                    'professionnel' => [
                        'S\'intégrer dans l\'équipe entreprise',
                        'Comprendre les enjeux métier',
                        'Développer son réseau professionnel',
                        'Participer aux réunions d\'équipe',
                        'Proposer des solutions innovantes',
                        'Gérer son temps efficacement',
                        'Communiquer avec les clients',
                        'Accompagner la formation de nouveaux collaborateurs',
                    ],
                ];

                foreach (['technique', 'projet', 'professionnel'] as $category) {
                    $categoryObjectives = $faker->randomElements(
                        $objectiveTemplates[$category],
                        min(3, ceil($numCompleted / 3)),
                    );

                    foreach ($categoryObjectives as $objective) {
                        $completedObjectives[] = [
                            'category' => $category,
                            'objective' => $objective,
                            'completed_at' => $faker->dateTimeBetween($periodDate, $periodDate)->format('Y-m-d'),
                        ];
                    }
                }

                // Generate pending objectives (what's currently being worked on)
                $pendingObjectives = [];
                $nextObjectives = array_slice($objectiveTemplates['technique'], $numCompleted, 2);
                foreach ($nextObjectives as $objective) {
                    $pendingObjectives[] = [
                        'category' => 'technique',
                        'objective' => $objective,
                        'target_date' => $faker->dateTimeBetween($periodDate, (clone $periodDate)->modify('+2 months'))->format('Y-m-d'),
                        'priority' => $faker->randomElement([1, 2, 3, 4, 5]),
                    ];
                }

                // Generate upcoming objectives (planned for next periods)
                $upcomingObjectives = [];
                $futureObjectives = array_slice($objectiveTemplates['professionnel'], 0, 2);
                foreach ($futureObjectives as $objective) {
                    $upcomingObjectives[] = [
                        'category' => 'professionnel',
                        'objective' => $objective,
                        'start_date' => $faker->dateTimeBetween((clone $periodDate)->modify('+1 month'), (clone $periodDate)->modify('+3 months'))->format('Y-m-d'),
                    ];
                }

                $assessment->setCompletedObjectives($completedObjectives)
                    ->setPendingObjectives($pendingObjectives)
                    ->setUpcomingObjectives($upcomingObjectives)
                ;

                // Generate difficulties (less common, more likely in early periods)
                $difficulties = [];
                if ($faker->boolean(30 - $index * 5)) { // Decreasing probability over time
                    $difficultyTemplates = [
                        'technique' => [
                            'Compréhension des concepts avancés',
                            'Maîtrise des outils de développement',
                            'Gestion de la complexité du code',
                            'Optimisation des performances',
                        ],
                        'organisationnel' => [
                            'Gestion du temps entre centre et entreprise',
                            'Adaptation aux méthodes de travail',
                            'Communication avec l\'équipe',
                            'Compréhension des enjeux business',
                        ],
                        'personnel' => [
                            'Gestion du stress lié aux évaluations',
                            'Confiance en soi lors des présentations',
                            'Motivation lors des périodes difficiles',
                            'Équilibre vie privée/professionnelle',
                        ],
                    ];

                    $difficultyCategory = $faker->randomElement(['technique', 'organisationnel', 'personnel']);
                    $difficulty = $faker->randomElement($difficultyTemplates[$difficultyCategory]);

                    $difficulties[] = [
                        'area' => $difficultyCategory,
                        'description' => $difficulty,
                        'severity' => $faker->numberBetween(1, 5),
                    ];
                }

                // Generate support needed (correlated with difficulties)
                $supportNeeded = [];
                if (!empty($difficulties) || $faker->boolean(20)) {
                    $supportTypes = [
                        'pedagogique' => [
                            'Cours de soutien individualisés',
                            'Exercices pratiques supplémentaires',
                            'Documentation technique adaptée',
                            'Sessions de révision en groupe',
                        ],
                        'technique' => [
                            'Accès à des outils avancés',
                            'Formation sur les nouvelles technologies',
                            'Mentorat technique par un senior',
                            'Participation à des projets encadrés',
                        ],
                        'organisationnel' => [
                            'Aide à la planification du travail',
                            'Coordination centre-entreprise renforcée',
                            'Clarification des objectifs',
                            'Amélioration des processus de communication',
                        ],
                        'personnel' => [
                            'Accompagnement par un conseiller',
                            'Techniques de gestion du stress',
                            'Développement de la confiance en soi',
                            'Équilibrage de la charge de travail',
                        ],
                    ];

                    $supportType = $faker->randomElement(['pedagogique', 'technique', 'organisationnel', 'personnel']);
                    $support = $faker->randomElement($supportTypes[$supportType]);

                    $supportNeeded[] = [
                        'type' => $supportType,
                        'description' => $support,
                        'urgency' => $faker->numberBetween(1, 5),
                    ];
                }

                $assessment->setDifficulties($difficulties)
                    ->setSupportNeeded($supportNeeded)
                ;

                // Generate next steps
                $nextStepsTemplates = [
                    'Continuer le développement des compétences techniques sur les technologies {tech}',
                    "Renforcer l'autonomie sur les projets complexes",
                    'Développer les compétences en gestion de projet',
                    "Préparer la soutenance de fin d'alternance",
                    'Approfondir les connaissances métier du secteur',
                    "Participer à des projets transversaux dans l'entreprise",
                    'Développer son réseau professionnel',
                    'Préparer sa future prise de poste',
                ];

                $nextSteps = str_replace(
                    '{tech}',
                    $faker->randomElement(['React', 'Symfony', 'Vue.js', 'Node.js', 'Python', 'Java']),
                    $faker->randomElement($nextStepsTemplates),
                );
                $assessment->setNextSteps($nextSteps);

                // Generate skills matrix
                $skillsMatrix = $this->generateSkillsMatrix($index, $faker);
                $assessment->setSkillsMatrix($skillsMatrix);

                // Calculate risk level based on progression and difficulties
                $assessment->calculateRiskLevel();

                $manager->persist($assessment);
                $assessmentsCreated++;

                // Periodic flush for memory management
                if ($assessmentsCreated % 20 === 0) {
                    $manager->flush();
                    // Don't clear for this fixture as we need entity references
                }
            }
        }

        $manager->flush();
        echo "✅ Created {$assessmentsCreated} progress assessments\n";
    }

    public function getDependencies(): array
    {
        return [
            StudentFixtures::class,
            AlternanceFixtures::class, // Need contracts first
            StudentProgressFixtures::class,
        ];
    }

    private function generateSkillsMatrix(int $progressIndex, $faker): array
    {
        $skillsCategories = [
            'technique' => [
                'PROG_LANG' => 'Langages de programmation',
                'WEB_DEV' => 'Développement web',
                'DATABASE' => 'Bases de données',
                'TESTING' => 'Tests et qualité',
                'DEVOPS' => 'DevOps et déploiement',
                'SECURITY' => 'Sécurité informatique',
            ],
            'transversale' => [
                'COMMUNICATION' => 'Communication',
                'TEAMWORK' => 'Travail en équipe',
                'PROBLEM_SOLVING' => 'Résolution de problèmes',
                'TIME_MANAGEMENT' => 'Gestion du temps',
                'LEARNING' => 'Capacité d\'apprentissage',
                'LEADERSHIP' => 'Leadership',
            ],
            'business' => [
                'DOMAIN_KNOWLEDGE' => 'Connaissance métier',
                'CLIENT_RELATION' => 'Relation client',
                'PROJECT_MGMT' => 'Gestion de projet',
                'INNOVATION' => 'Innovation',
                'QUALITY_FOCUS' => 'Focus qualité',
                'BUSINESS_VISION' => 'Vision business',
            ],
        ];

        $matrix = [];

        foreach ($skillsCategories as $category => $skills) {
            foreach ($skills as $skillCode => $skillName) {
                // Progressive skill development over time
                $baseLevel = 1 + $progressIndex * 0.5; // Start at 1, increase by 0.5 each period
                $randomVariation = $faker->randomFloat(1, -0.3, 0.7);
                $currentLevel = max(1, min(5, $baseLevel + $randomVariation));

                $targetLevel = min(5, $currentLevel + $faker->randomFloat(1, 0.5, 1.5));

                $matrix[$skillCode] = [
                    'name' => $skillName,
                    'category' => $category,
                    'current_level' => number_format($currentLevel, 1),
                    'target_level' => number_format($targetLevel, 1),
                    'last_assessed' => $faker->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
                    'trend' => $this->calculateSkillTrend($currentLevel, $progressIndex),
                    'validation_source' => $faker->randomElement(['centre', 'entreprise', 'auto_evaluation', 'peer_review']),
                ];
            }
        }

        return $matrix;
    }

    private function calculateSkillTrend(float $currentLevel, int $progressIndex): string
    {
        if ($progressIndex === 0) {
            return 'new'; // First assessment
        }

        // Simulate some trend based on level and progression
        $growthFactor = $currentLevel + ($progressIndex * 0.3);

        if ($growthFactor > 3.5) {
            return 'improving';
        }
        if ($growthFactor < 2.0) {
            return 'declining';
        }

        return 'stable';
    }

    /**
     * Link existing StudentProgress records to AlternanceContracts.
     */
    private function linkProgressToContracts(ObjectManager $manager): void
    {
        $contracts = $manager->getRepository(AlternanceContract::class)->findAll();

        foreach ($contracts as $contract) {
            $student = $contract->getStudent();
            $studentProgress = $manager->getRepository(StudentProgress::class)->findOneBy(['student' => $student]);

            if ($studentProgress && !$studentProgress->getAlternanceContract()) {
                $studentProgress->setAlternanceContract($contract);
                $manager->persist($studentProgress);
            }
        }

        $manager->flush();
        echo "✅ Linked StudentProgress records to AlternanceContracts\n";
    }
}
