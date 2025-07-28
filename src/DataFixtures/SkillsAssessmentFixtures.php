<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Alternance\MissionAssignment;
use App\Entity\Alternance\SkillsAssessment;
use App\Entity\User\Mentor;
use App\Entity\User\Student;
use App\Entity\User\Teacher;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

/**
 * SkillsAssessmentFixtures.
 *
 * Generates realistic skills assessment data for testing the cross-evaluation system
 * between training center and company environments.
 */
class SkillsAssessmentFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        $students = $manager->getRepository(Student::class)->findAll();
        $teachers = $manager->getRepository(Teacher::class)->findAll();
        $mentors = $manager->getRepository(Mentor::class)->findAll();
        $missions = $manager->getRepository(MissionAssignment::class)->findAll();

        if (empty($students) || empty($teachers) || empty($mentors)) {
            echo "Warning: Students, Teachers, or Mentors not found. Skipping SkillsAssessmentFixtures.\n";

            return;
        }

        // Technical skills framework for IT/Digital training
        $technicalSkills = [
            'PROG_JAVA' => 'Programmation Java',
            'PROG_PHP' => 'Programmation PHP/Symfony',
            'PROG_JS' => 'Développement JavaScript',
            'DB_SQL' => 'Bases de données SQL',
            'DB_NOSQL' => 'Bases de données NoSQL',
            'WEB_HTML' => 'HTML/CSS',
            'WEB_REACT' => 'Framework React',
            'WEB_VUE' => 'Framework Vue.js',
            'API_REST' => 'APIs REST',
            'API_GRAPH' => 'GraphQL',
            'DEVOPS_GIT' => 'Gestion de version Git',
            'DEVOPS_CI' => 'Intégration continue',
            'DEVOPS_DOCKER' => 'Containerisation Docker',
            'CLOUD_AWS' => 'Amazon Web Services',
            'CLOUD_AZURE' => 'Microsoft Azure',
            'SECURITY_WEB' => 'Sécurité web',
            'TESTING_UNIT' => 'Tests unitaires',
            'TESTING_INT' => 'Tests d\'intégration',
        ];

        $transversalSkills = [
            'COMM_ORAL' => 'Communication orale',
            'COMM_ECRIT' => 'Communication écrite',
            'PROB_SOLVING' => 'Résolution de problèmes',
            'TEAM_WORK' => 'Travail en équipe',
            'AUTO_LEARN' => 'Apprentissage autonome',
            'TIME_MGMT' => 'Gestion du temps',
            'STRESS_MGMT' => 'Gestion du stress',
            'LEADERSHIP' => 'Leadership',
            'INNOVATION' => 'Innovation et créativité',
            'CUSTOMER_REL' => 'Relations client',
            'PROJECT_MGMT' => 'Gestion de projet',
            'ADAPT_CHANGE' => 'Adaptation au changement',
        ];

        $assessmentTypes = ['formative', 'sommative', 'certification'];
        $contexts = ['centre', 'entreprise', 'mixte'];
        $overallRatings = ['excellent', 'good', 'satisfactory', 'needs_improvement', 'unsatisfactory'];

        $assessmentsCreated = 0;
        $maxAssessments = 150; // Limit for performance

        foreach ($students as $student) {
            if ($assessmentsCreated >= $maxAssessments) {
                break;
            }

            $numAssessments = $faker->numberBetween(2, 8); // 2-8 assessments per student
            $assessmentDates = [];

            // Generate spread-out assessment dates over past 12 months
            for ($i = 0; $i < $numAssessments; $i++) {
                $assessmentDates[] = $faker->dateTimeBetween('-12 months', '-1 week');
            }
            sort($assessmentDates); // Chronological order

            foreach ($assessmentDates as $index => $assessmentDate) {
                if ($assessmentsCreated >= $maxAssessments) {
                    break;
                }

                $assessment = new SkillsAssessment();

                // Basic information
                $assessment->setStudent($student)
                    ->setAssessmentType($faker->randomElement($assessmentTypes))
                    ->setContext($faker->randomElement($contexts))
                    ->setAssessmentDate(DateTimeImmutable::createFromMutable($assessmentDate))
                ;

                // Assign evaluators based on context
                if (in_array($assessment->getContext(), ['centre', 'mixte'], true)) {
                    $assessment->setCenterEvaluator($faker->randomElement($teachers));
                }

                if (in_array($assessment->getContext(), ['entreprise', 'mixte'], true)) {
                    $assessment->setMentorEvaluator($faker->randomElement($mentors));
                }

                // Link to mission if company context
                if (!empty($missions) && in_array($assessment->getContext(), ['entreprise', 'mixte'], true)) {
                    $studentMissions = array_filter($missions, static fn ($mission) => $mission->getStudent() === $student);
                    if (!empty($studentMissions)) {
                        $assessment->setRelatedMission($faker->randomElement($studentMissions));
                    }
                }

                // Generate skills evaluated (mix of technical and transversal)
                $selectedTechnical = $faker->randomElements(
                    array_keys($technicalSkills),
                    $faker->numberBetween(3, 8),
                );
                $selectedTransversal = $faker->randomElements(
                    array_keys($transversalSkills),
                    $faker->numberBetween(2, 5),
                );

                $skillsEvaluated = [];
                $centerScores = [];
                $companyScores = [];
                $globalCompetencies = [];

                // Technical skills evaluation
                foreach ($selectedTechnical as $skillCode) {
                    $skillName = $technicalSkills[$skillCode];
                    $skillsEvaluated[] = [
                        'code' => $skillCode,
                        'name' => $skillName,
                        'category' => 'technique',
                        'weight' => $faker->randomFloat(1, 0.5, 3.0),
                    ];

                    // Progressive improvement over time
                    $baseScore = $faker->numberBetween(1, 5);
                    $progressionBonus = min(2, $index * 0.3); // Gradual improvement
                    $finalScore = min(5, $baseScore + $progressionBonus);

                    if (in_array($assessment->getContext(), ['centre', 'mixte'], true)) {
                        $centerScores[$skillCode] = [
                            'score' => number_format($finalScore, 1),
                            'comment' => $this->generateSkillComment($skillName, $finalScore, 'centre'),
                            'evidence' => $this->generateEvidence($skillName, 'centre'),
                        ];
                    }

                    if (in_array($assessment->getContext(), ['entreprise', 'mixte'], true)) {
                        $variation = $faker->randomFloat(1, -0.5, 0.5);
                        $companyScore = max(1, min(5, $finalScore + $variation));
                        $companyScores[$skillCode] = [
                            'score' => number_format($companyScore, 1),
                            'comment' => $this->generateSkillComment($skillName, $companyScore, 'entreprise'),
                            'evidence' => $this->generateEvidence($skillName, 'entreprise'),
                        ];
                    }
                }

                // Transversal skills evaluation
                foreach ($selectedTransversal as $skillCode) {
                    $skillName = $transversalSkills[$skillCode];
                    $skillsEvaluated[] = [
                        'code' => $skillCode,
                        'name' => $skillName,
                        'category' => 'transversale',
                        'weight' => $faker->randomFloat(1, 1.0, 2.0),
                    ];

                    $baseScore = $faker->numberBetween(2, 5);
                    $progressionBonus = min(1, $index * 0.2);
                    $finalScore = min(5, $baseScore + $progressionBonus);

                    if (in_array($assessment->getContext(), ['centre', 'mixte'], true)) {
                        $centerScores[$skillCode] = [
                            'score' => number_format($finalScore, 1),
                            'comment' => $this->generateSkillComment($skillName, $finalScore, 'centre'),
                            'evidence' => $this->generateEvidence($skillName, 'centre'),
                        ];
                    }

                    if (in_array($assessment->getContext(), ['entreprise', 'mixte'], true)) {
                        $variation = $faker->randomFloat(1, -0.3, 0.3);
                        $companyScore = max(1, min(5, $finalScore + $variation));
                        $companyScores[$skillCode] = [
                            'score' => number_format($companyScore, 1),
                            'comment' => $this->generateSkillComment($skillName, $companyScore, 'entreprise'),
                            'evidence' => $this->generateEvidence($skillName, 'entreprise'),
                        ];
                    }
                }

                // Global competencies assessment
                $competencyAreas = [
                    'technical_mastery' => 'Maîtrise technique',
                    'project_execution' => 'Exécution de projet',
                    'collaboration' => 'Collaboration',
                    'communication' => 'Communication',
                    'problem_solving' => 'Résolution de problèmes',
                    'innovation' => 'Innovation',
                ];

                foreach ($competencyAreas as $competencyCode => $competencyName) {
                    $baseLevel = $faker->numberBetween(1, 4);
                    $progressionBonus = min(2, $index * 0.25);
                    $currentLevel = min(5, $baseLevel + $progressionBonus);

                    $globalCompetencies[$competencyCode] = [
                        'name' => $competencyName,
                        'current_level' => (int) $currentLevel,
                        'target_level' => min(5, $currentLevel + $faker->numberBetween(0, 2)),
                        'development_priority' => $faker->randomElement(['high', 'medium', 'low']),
                        'assessment_notes' => $this->generateCompetencyNotes($competencyName, $currentLevel),
                    ];
                }

                // Set all data
                $assessment->setSkillsEvaluated($skillsEvaluated)
                    ->setCenterScores($centerScores)
                    ->setCompanyScores($companyScores)
                    ->setGlobalCompetencies($globalCompetencies)
                ;

                // Add evaluator comments
                if ($assessment->getCenterEvaluator()) {
                    $assessment->setCenterComments($this->generateEvaluatorComments('centre', $assessment));
                }

                if ($assessment->getMentorEvaluator()) {
                    $assessment->setMentorComments($this->generateEvaluatorComments('entreprise', $assessment));
                }

                // Generate development plan
                $assessment->setDevelopmentPlan($this->generateDevelopmentPlan($globalCompetencies, $assessment));

                // Calculate overall rating
                $avgScore = $this->calculateAverageScore($centerScores, $companyScores);
                $overallRating = $this->determineOverallRating($avgScore);
                $assessment->setOverallRating($overallRating);

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
        echo "✅ Created {$assessmentsCreated} skills assessments\n";
    }

    public function getDependencies(): array
    {
        return [
            StudentFixtures::class,
            TeacherFixtures::class,
            MentorFixtures::class,
            MissionAssignmentFixtures::class,
        ];
    }

    private function generateSkillComment(string $skillName, float $score, string $context): string
    {
        $contextPhrases = [
            'centre' => [
                'excellent' => 'Maîtrise parfaite démontrée en cours et TP',
                'good' => 'Bonne compréhension avec mise en pratique réussie',
                'satisfactory' => 'Compétence acquise, à consolider par la pratique',
                'needs_improvement' => 'Compréhension partielle, nécessite un accompagnement',
                'unsatisfactory' => 'Difficultés importantes, révision complète nécessaire',
            ],
            'entreprise' => [
                'excellent' => 'Performance exceptionnelle en situation professionnelle',
                'good' => 'Autonomie confirmée dans les tâches liées à cette compétence',
                'satisfactory' => 'Application correcte avec supervision occasionnelle',
                'needs_improvement' => 'Requiert encore de l\'accompagnement en situation réelle',
                'unsatisfactory' => 'Difficultés persistantes malgré l\'encadrement',
            ],
        ];

        $level = match (true) {
            $score >= 4.5 => 'excellent',
            $score >= 3.5 => 'good',
            $score >= 2.5 => 'satisfactory',
            $score >= 1.5 => 'needs_improvement',
            default => 'unsatisfactory'
        };

        return $contextPhrases[$context][$level] ?? 'Évaluation en cours.';
    }

    private function generateEvidence(string $skillName, string $context): array
    {
        $evidenceTypes = [
            'centre' => [
                'Projet réalisé en TP',
                'Exercices pratiques validés',
                'Présentation technique',
                'Code source commenté',
                'Documentation technique produite',
                'Quiz et évaluations théoriques',
            ],
            'entreprise' => [
                'Tâche réalisée en autonomie',
                'Contribution à un projet client',
                'Résolution de problème technique',
                'Amélioration de processus',
                'Formation d\'un collègue',
                'Participation à une réunion technique',
            ],
        ];

        $faker = Factory::create('fr_FR');

        return $faker->randomElements($evidenceTypes[$context], $faker->numberBetween(1, 3));
    }

    private function generateCompetencyNotes(string $competencyName, float $currentLevel): string
    {
        $notes = [
            1 => "Compétence en cours d'acquisition pour {$competencyName}.",
            2 => "Bases établies pour {$competencyName}, développement en cours.",
            3 => "Niveau satisfaisant atteint pour {$competencyName}.",
            4 => "Bonne maîtrise de {$competencyName} démontrée.",
            5 => "Excellence et expertise confirmées pour {$competencyName}.",
        ];

        return $notes[$currentLevel] ?? "Évaluation de {$competencyName} en cours.";
    }

    private function generateEvaluatorComments(string $context, SkillsAssessment $assessment): string
    {
        $faker = Factory::create('fr_FR');

        $templates = [
            'centre' => [
                "L'alternant démontre {progression} dans l'acquisition des compétences techniques. {detail_technique} {encouragement}",
                'Évolution {qualificatif} observée lors des travaux pratiques. {observation_specifique} {recommandation}',
                'Performance {niveau} en cours théoriques et applications pratiques. {point_fort} {axe_amelioration}',
            ],
            'entreprise' => [
                "L'alternant s'intègre {integration} dans l'équipe et montre {autonomie} sur ses missions. {realisation} {perspective}",
                "Adaptation {rapidite} aux méthodes de travail de l'entreprise. {competence_cle} {evolution_souhaitee}",
                'Contribution {qualite} aux projets avec {niveau_supervision}. {satisfaction} {objectif_suivant}',
            ],
        ];

        $variables = [
            'centre' => [
                'progression' => ['une progression constante', 'des progrès significatifs', 'une évolution positive', 'une montée en compétences'],
                'qualificatif' => ['positive', 'encourageante', 'notable', 'satisfaisante'],
                'niveau' => ['solide', 'convenable', 'remarquable', 'correcte'],
                'detail_technique' => ['La maîtrise des outils se confirme.', 'Les concepts théoriques sont bien assimilés.', 'Les travaux pratiques sont de qualité.'],
                'observation_specifique' => ['L\'approche méthodologique s\'améliore.', 'La qualité du code progresse.', 'La documentation technique est soignée.'],
                'point_fort' => ['Points forts identifiés dans la résolution de problèmes.', 'Rigueur technique appréciée.', 'Créativité dans les solutions proposées.'],
                'encouragement' => ['À maintenir dans cette voie.', 'Très encourageant pour la suite.', 'Objectifs pédagogiques en bonne voie.'],
                'recommandation' => ['Continuer les exercices pratiques.', 'Approfondir certains aspects théoriques.', 'Développer l\'autonomie.'],
                'axe_amelioration' => ['Points d\'amélioration identifiés pour la prochaine période.', 'Axes de progression définis ensemble.', 'Objectifs adaptés aux besoins.'],
            ],
            'entreprise' => [
                'integration' => ['très bien', 'parfaitement', 'rapidement', 'naturellement'],
                'autonomie' => ['une autonomie croissante', 'une bonne autonomie', 'de l\'initiative', 'une autonomie satisfaisante'],
                'rapidite' => ['rapide', 'progressive', 'efficace', 'réussie'],
                'qualite' => ['positive', 'constructive', 'appréciée', 'valorisée'],
                'niveau_supervision' => ['un encadrement minimal', 'une supervision adaptée', 'un accompagnement ciblé', 'un suivi personnalisé'],
                'realisation' => ['Missions confiées réalisées avec succès.', 'Objectifs fixés atteints dans les délais.', 'Qualité du travail appréciée par l\'équipe.'],
                'competence_cle' => ['Compétences techniques confirmées en situation.', 'Soft skills développées dans le contexte professionnel.', 'Capacité d\'apprentissage démontrée.'],
                'satisfaction' => ['Très satisfait de l\'évolution.', 'Retours positifs de l\'équipe.', 'Intégration réussie dans les projets.'],
                'perspective' => ['Perspectives d\'évolution encourageantes.', 'Potentiel confirmé pour la suite.', 'Objectifs ambitieux pour la prochaine période.'],
                'evolution_souhaitee' => ['Montée en compétences souhaitée sur les projets avancés.', 'Élargissement du périmètre d\'intervention envisagé.', 'Prise de responsabilités progressives prévue.'],
                'objectif_suivant' => ['Prochaine étape : autonomie complète sur le projet.', 'Objectif : contribution à l\'architecture technique.', 'But : encadrement d\'un stagiaire junior.'],
            ],
        ];

        $template = $faker->randomElement($templates[$context]);
        $contextVars = $variables[$context];

        // Replace placeholders with random values
        foreach ($contextVars as $key => $options) {
            $template = str_replace('{' . $key . '}', $faker->randomElement($options), $template);
        }

        return $template;
    }

    private function generateDevelopmentPlan(array $globalCompetencies, SkillsAssessment $assessment): array
    {
        $faker = Factory::create('fr_FR');
        $plan = [
            'short_term_objectives' => [],
            'medium_term_objectives' => [],
            'long_term_objectives' => [],
            'recommended_actions' => [],
            'resources_needed' => [],
            'success_indicators' => [],
        ];

        // Identify competencies needing development
        $needsDevelopment = array_filter($globalCompetencies, static fn ($comp) => $comp['current_level'] < $comp['target_level']);

        $priorityCompetencies = array_filter($needsDevelopment, static fn ($comp) => $comp['development_priority'] === 'high');

        // Short-term objectives (1-3 months)
        foreach (array_slice($priorityCompetencies, 0, 2) as $code => $competency) {
            $plan['short_term_objectives'][] = [
                'competency' => $competency['name'],
                'current_level' => $competency['current_level'],
                'target_level' => min($competency['current_level'] + 1, $competency['target_level']),
                'deadline' => $faker->dateTimeBetween('+1 month', '+3 months')->format('Y-m-d'),
                'specific_goal' => $this->generateSpecificGoal($competency['name'], 'short_term'),
            ];
        }

        // Medium-term objectives (3-6 months)
        foreach (array_slice($needsDevelopment, 0, 3) as $code => $competency) {
            $plan['medium_term_objectives'][] = [
                'competency' => $competency['name'],
                'target_level' => $competency['target_level'],
                'deadline' => $faker->dateTimeBetween('+3 months', '+6 months')->format('Y-m-d'),
                'milestone' => $this->generateMilestone($competency['name']),
            ];
        }

        // Long-term objectives (6-12 months)
        $plan['long_term_objectives'][] = [
            'goal' => 'Atteindre l\'autonomie complète sur l\'ensemble des compétences du référentiel',
            'target_date' => $faker->dateTimeBetween('+6 months', '+12 months')->format('Y-m-d'),
            'success_criteria' => 'Évaluation globale excellente avec validation par le tuteur entreprise',
        ];

        // Recommended actions
        $actions = [
            'Participation à des formations complémentaires',
            'Travail sur des projets de complexité croissante',
            'Mentorat par un senior de l\'équipe',
            'Présentation de travaux techniques à l\'équipe',
            'Veille technologique et partage de connaissances',
            'Participation à des communautés professionnelles',
        ];
        $plan['recommended_actions'] = $faker->randomElements($actions, $faker->numberBetween(3, 5));

        // Resources needed
        $resources = [
            'Accès à des plateformes de formation en ligne',
            'Documentation technique spécialisée',
            'Environnement de développement avancé',
            'Temps dédié à la formation (2h/semaine)',
            'Feedback régulier du tuteur entreprise',
            'Participation à des conférences/webinaires',
        ];
        $plan['resources_needed'] = $faker->randomElements($resources, $faker->numberBetween(2, 4));

        // Success indicators
        $indicators = [
            'Augmentation de l\'autonomie sur les tâches complexes',
            'Diminution du temps de résolution des problèmes',
            'Amélioration de la qualité des livrables',
            'Retours positifs de l\'équipe et du tuteur',
            'Capacité à former/accompagner d\'autres alternants',
            'Innovation dans les solutions proposées',
        ];
        $plan['success_indicators'] = $faker->randomElements($indicators, $faker->numberBetween(3, 4));

        return $plan;
    }

    private function generateSpecificGoal(string $competencyName, string $timeframe): string
    {
        $goals = [
            'short_term' => [
                'Maîtrise technique' => 'Maîtriser les concepts de base et outils essentiels',
                'Exécution de projet' => 'Planifier et exécuter une tâche complète en autonomie',
                'Collaboration' => 'Participer activement aux réunions d\'équipe',
                'Communication' => 'Présenter ses travaux de manière claire et structurée',
                'Résolution de problèmes' => 'Identifier et résoudre des problèmes simples',
                'Innovation' => 'Proposer des améliorations sur les processus existants',
            ],
        ];

        return $goals[$timeframe][$competencyName] ?? "Développer {$competencyName} selon les objectifs définis";
    }

    private function generateMilestone(string $competencyName): string
    {
        $milestones = [
            'Maîtrise technique' => 'Validation des compétences par un projet technique complet',
            'Exécution de projet' => 'Gestion autonome d\'un projet de bout en bout',
            'Collaboration' => 'Animation d\'une réunion ou formation d\'un pair',
            'Communication' => 'Présentation technique devant un client ou en conférence',
            'Résolution de problèmes' => 'Résolution d\'incidents critiques en autonomie',
            'Innovation' => 'Implémentation d\'une innovation reconnue par l\'équipe',
        ];

        return $milestones[$competencyName] ?? "Atteindre le niveau expert pour {$competencyName}";
    }

    private function calculateAverageScore(array $centerScores, array $companyScores): float
    {
        $allScores = [];

        foreach ($centerScores as $score) {
            $allScores[] = (float) $score['score'];
        }

        foreach ($companyScores as $score) {
            $allScores[] = (float) $score['score'];
        }

        return empty($allScores) ? 0.0 : array_sum($allScores) / count($allScores);
    }

    private function determineOverallRating(float $avgScore): string
    {
        return match (true) {
            $avgScore >= 4.5 => 'excellent',
            $avgScore >= 3.5 => 'good',
            $avgScore >= 2.5 => 'satisfactory',
            $avgScore >= 1.5 => 'needs_improvement',
            default => 'unsatisfactory'
        };
    }
}
