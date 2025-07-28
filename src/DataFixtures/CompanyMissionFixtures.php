<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Alternance\CompanyMission;
use App\Entity\User\Mentor;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class CompanyMissionFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // Get mentors
        $mentors = $manager->getRepository(Mentor::class)->findAll();

        if (empty($mentors)) {
            return;
        }

        // Mission templates by complexity and term
        $missionTemplates = $this->getMissionTemplates();

        $missionCount = 0;

        foreach ($mentors as $mentor) {
            // Each mentor creates 5-12 missions with proper progression
            $missionsToCreate = $faker->numberBetween(5, 12);

            // Ensure proper distribution across terms and complexity
            $termDistribution = ['court' => 40, 'moyen' => 35, 'long' => 25]; // Percentage
            $complexityDistribution = ['debutant' => 50, 'intermediaire' => 35, 'avance' => 15]; // Percentage

            $createdMissions = [];

            for ($i = 0; $i < $missionsToCreate; $i++) {
                $mission = new CompanyMission();

                // Determine term and complexity based on distribution
                $term = $this->selectWeightedRandom($termDistribution);
                $complexity = $this->selectWeightedRandom($complexityDistribution);

                // Get template for this term/complexity combination
                $template = $faker->randomElement($missionTemplates[$term][$complexity]);

                // Basic properties
                $mission->setTitle($template['title']);
                $mission->setDescription($template['description']);
                $mission->setContext($this->generateContext($faker, $mentor));
                $mission->setSupervisor($mentor);
                $mission->setComplexity($complexity);
                $mission->setTerm($term);
                $mission->setDuration($faker->randomElement($template['durations']));
                $mission->setDepartment($faker->randomElement(array_keys(CompanyMission::DEPARTMENTS)));

                // Generate objectives (3-6 objectives)
                $objectives = $template['objectives'];
                $selectedObjectives = $faker->randomElements($objectives, $faker->numberBetween(3, min(6, count($objectives))));
                $mission->setObjectives($selectedObjectives);

                // Generate required skills
                $requiredSkills = $template['required_skills'];
                $selectedRequiredSkills = $faker->randomElements($requiredSkills, $faker->numberBetween(2, min(5, count($requiredSkills))));
                $mission->setRequiredSkills($selectedRequiredSkills);

                // Generate skills to acquire
                $skillsToAcquire = $template['skills_to_acquire'];
                $selectedSkillsToAcquire = $faker->randomElements($skillsToAcquire, $faker->numberBetween(3, min(7, count($skillsToAcquire))));
                $mission->setSkillsToAcquire($selectedSkillsToAcquire);

                // Generate prerequisites
                $prerequisites = $this->generatePrerequisites($faker, $complexity, $term);
                $mission->setPrerequisites($prerequisites);

                // Generate evaluation criteria
                $evaluationCriteria = $template['evaluation_criteria'];
                $selectedCriteria = $faker->randomElements($evaluationCriteria, $faker->numberBetween(3, min(6, count($evaluationCriteria))));
                $mission->setEvaluationCriteria($selectedCriteria);

                // Set order index
                $orderIndex = $this->getNextOrderIndex($createdMissions, $term, $complexity);
                $mission->setOrderIndex($orderIndex);

                // Set random creation date (last 6 months)
                $createdAt = DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-6 months', 'now'));
                $mission->setCreatedAt($createdAt);
                $mission->setUpdatedAt($createdAt);

                $manager->persist($mission);
                $createdMissions[] = $mission;
                $missionCount++;

                // Add mission reference for other fixtures
                $this->addReference('company-mission-' . $missionCount, $mission);
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            MentorFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['alternance', 'missions'];
    }

    /**
     * Get mission templates organized by term and complexity.
     */
    private function getMissionTemplates(): array
    {
        return [
            'court' => [
                'debutant' => [
                    [
                        'title' => 'Découverte du poste et des outils',
                        'description' => 'Première approche du poste de travail, familiarisation avec les outils et les procédures de base de l\'entreprise.',
                        'objectives' => [
                            'Prendre connaissance de l\'environnement de travail',
                            'Maîtriser les outils de base',
                            'Comprendre l\'organisation de l\'équipe',
                            'Identifier les interlocuteurs clés',
                            'Respecter les procédures de sécurité',
                        ],
                        'required_skills' => [
                            'Curiosité et capacité d\'adaptation',
                            'Capacités de communication',
                            'Sens de l\'observation',
                        ],
                        'skills_to_acquire' => [
                            'Utilisation des outils métier',
                            'Compréhension des processus internes',
                            'Autonomie dans les tâches simples',
                            'Communication professionnelle',
                        ],
                        'evaluation_criteria' => [
                            'Rapidité d\'adaptation',
                            'Qualité des questions posées',
                            'Respect des consignes',
                            'Initiative dans l\'apprentissage',
                        ],
                        'durations' => ['1-2 semaines', '3-4 semaines'],
                    ],
                    [
                        'title' => 'Support aux équipes opérationnelles',
                        'description' => 'Assistance dans les tâches courantes des équipes, participation aux activités quotidiennes sous supervision.',
                        'objectives' => [
                            'Assister les équipes dans leurs tâches',
                            'Apprendre les procédures standards',
                            'Développer l\'esprit d\'équipe',
                            'Contribuer aux objectifs collectifs',
                        ],
                        'required_skills' => [
                            'Esprit d\'équipe',
                            'Volonté d\'apprendre',
                            'Ponctualité et assiduité',
                        ],
                        'skills_to_acquire' => [
                            'Travail en équipe',
                            'Gestion des priorités simples',
                            'Communication opérationnelle',
                            'Respect des délais',
                        ],
                        'evaluation_criteria' => [
                            'Intégration dans l\'équipe',
                            'Qualité du travail fourni',
                            'Respect des échéances',
                            'Proactivité',
                        ],
                        'durations' => ['2-3 semaines', '1 mois'],
                    ],
                ],
                'intermediaire' => [
                    [
                        'title' => 'Gestion d\'un projet simple',
                        'description' => 'Prise en charge d\'un petit projet avec objectifs définis et ressources allouées.',
                        'objectives' => [
                            'Planifier les étapes du projet',
                            'Coordonner les différents intervenants',
                            'Respecter les délais et le budget',
                            'Communiquer sur l\'avancement',
                        ],
                        'required_skills' => [
                            'Bases de la gestion de projet',
                            'Capacité d\'organisation',
                            'Leadership naissant',
                        ],
                        'skills_to_acquire' => [
                            'Planification et suivi de projet',
                            'Coordination d\'équipe',
                            'Gestion des risques simples',
                            'Reporting et communication',
                        ],
                        'evaluation_criteria' => [
                            'Respect des délais',
                            'Qualité des livrables',
                            'Capacité de coordination',
                            'Gestion des difficultés',
                        ],
                        'durations' => ['1 mois', '1-2 mois'],
                    ],
                ],
                'avance' => [
                    [
                        'title' => 'Amélioration d\'un processus existant',
                        'description' => 'Analyse critique d\'un processus en place et proposition d\'améliorations concrètes.',
                        'objectives' => [
                            'Analyser le processus existant',
                            'Identifier les points d\'amélioration',
                            'Proposer des solutions innovantes',
                            'Mesurer l\'impact des améliorations',
                        ],
                        'required_skills' => [
                            'Esprit analytique',
                            'Capacité d\'innovation',
                            'Maîtrise des outils d\'analyse',
                        ],
                        'skills_to_acquire' => [
                            'Analyse de processus',
                            'Proposition d\'améliorations',
                            'Conduite du changement',
                            'Mesure de performance',
                        ],
                        'evaluation_criteria' => [
                            'Pertinence de l\'analyse',
                            'Créativité des solutions',
                            'Faisabilité des propositions',
                            'Impact mesurable',
                        ],
                        'durations' => ['1-2 mois', '2-3 mois'],
                    ],
                ],
            ],
            'moyen' => [
                'debutant' => [
                    [
                        'title' => 'Participation à un projet départemental',
                        'description' => 'Intégration dans un projet de moyenne envergure avec responsabilités définies.',
                        'objectives' => [
                            'Contribuer activement au projet',
                            'Développer des compétences métier',
                            'Travailler en mode projet',
                            'Acquérir une vision élargie',
                        ],
                        'required_skills' => [
                            'Bases métier solides',
                            'Capacité de travail en équipe projet',
                            'Adaptabilité',
                        ],
                        'skills_to_acquire' => [
                            'Gestion de projet avancée',
                            'Compétences métier spécialisées',
                            'Travail collaboratif étendu',
                            'Vision transversale',
                        ],
                        'evaluation_criteria' => [
                            'Contribution au projet',
                            'Développement des compétences',
                            'Collaboration efficace',
                            'Appropriation des enjeux',
                        ],
                        'durations' => ['2-3 mois', '3-4 mois'],
                    ],
                ],
                'intermediaire' => [
                    [
                        'title' => 'Coordination d\'une équipe projet',
                        'description' => 'Responsabilité de coordination d\'une équipe projet avec gestion des ressources et planning.',
                        'objectives' => [
                            'Piloter une équipe projet',
                            'Gérer les ressources allouées',
                            'Assurer le suivi et le reporting',
                            'Résoudre les conflits et blocages',
                        ],
                        'required_skills' => [
                            'Leadership confirmé',
                            'Gestion de projet',
                            'Capacité de négociation',
                        ],
                        'skills_to_acquire' => [
                            'Management d\'équipe',
                            'Gestion de conflits',
                            'Pilotage de performance',
                            'Communication managériale',
                        ],
                        'evaluation_criteria' => [
                            'Efficacité du pilotage',
                            'Atteinte des objectifs',
                            'Qualité du management',
                            'Gestion des difficultés',
                        ],
                        'durations' => ['3-4 mois', '6 mois'],
                    ],
                ],
                'avance' => [
                    [
                        'title' => 'Développement d\'une nouvelle offre',
                        'description' => 'Conception et développement d\'une nouvelle offre produit/service pour l\'entreprise.',
                        'objectives' => [
                            'Analyser le marché et la concurrence',
                            'Concevoir l\'offre et son positionnement',
                            'Développer le business plan',
                            'Préparer le lancement commercial',
                        ],
                        'required_skills' => [
                            'Vision stratégique',
                            'Connaissance du marché',
                            'Capacité d\'innovation',
                        ],
                        'skills_to_acquire' => [
                            'Développement produit/service',
                            'Analyse concurrentielle',
                            'Business planning',
                            'Stratégie commerciale',
                        ],
                        'evaluation_criteria' => [
                            'Qualité de l\'analyse marché',
                            'Innovation de l\'offre',
                            'Viabilité économique',
                            'Potentiel commercial',
                        ],
                        'durations' => ['4-6 mois', '6 mois'],
                    ],
                ],
            ],
            'long' => [
                'debutant' => [
                    [
                        'title' => 'Suivi d\'un contrat client sur l\'année',
                        'description' => 'Responsabilité du suivi complet d\'un contrat client majeur sur une année complète.',
                        'objectives' => [
                            'Assurer la satisfaction client',
                            'Gérer la relation commerciale',
                            'Optimiser la rentabilité',
                            'Développer le compte client',
                        ],
                        'required_skills' => [
                            'Relation client',
                            'Compétences commerciales',
                            'Gestion de contrat',
                        ],
                        'skills_to_acquire' => [
                            'Account management',
                            'Négociation commerciale avancée',
                            'Gestion de la rentabilité',
                            'Développement commercial',
                        ],
                        'evaluation_criteria' => [
                            'Satisfaction client',
                            'Atteinte des objectifs commerciaux',
                            'Rentabilité du contrat',
                            'Développement du compte',
                        ],
                        'durations' => ['6 mois', '1 an'],
                    ],
                ],
                'intermediaire' => [
                    [
                        'title' => 'Pilotage d\'un centre de profit',
                        'description' => 'Responsabilité complète d\'un centre de profit avec objectifs de résultats.',
                        'objectives' => [
                            'Atteindre les objectifs financiers',
                            'Optimiser les ressources',
                            'Développer l\'activité',
                            'Manager les équipes',
                        ],
                        'required_skills' => [
                            'Gestion financière',
                            'Management d\'équipe',
                            'Vision business',
                        ],
                        'skills_to_acquire' => [
                            'Pilotage de centre de profit',
                            'Optimisation des ressources',
                            'Développement d\'activité',
                            'Management opérationnel',
                        ],
                        'evaluation_criteria' => [
                            'Atteinte des résultats financiers',
                            'Efficacité opérationnelle',
                            'Développement de l\'activité',
                            'Performance des équipes',
                        ],
                        'durations' => ['6 mois', '1 an'],
                    ],
                ],
                'avance' => [
                    [
                        'title' => 'Transformation digitale d\'un processus métier',
                        'description' => 'Conduite complète de la transformation digitale d\'un processus métier critique.',
                        'objectives' => [
                            'Analyser l\'existant et définir la cible',
                            'Piloter la transformation',
                            'Accompagner le changement',
                            'Mesurer les bénéfices',
                        ],
                        'required_skills' => [
                            'Transformation digitale',
                            'Conduite du changement',
                            'Gestion de projet complexe',
                        ],
                        'skills_to_acquire' => [
                            'Stratégie de transformation',
                            'Pilotage de grands projets',
                            'Change management',
                            'Mesure de la performance',
                        ],
                        'evaluation_criteria' => [
                            'Réussite de la transformation',
                            'Adoption par les utilisateurs',
                            'Bénéfices mesurables',
                            'Respect des délais et budget',
                        ],
                        'durations' => ['6 mois', '1 an'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Select a weighted random option.
     */
    private function selectWeightedRandom(array $weights): string
    {
        $totalWeight = array_sum($weights);
        $random = mt_rand(1, $totalWeight);

        $currentWeight = 0;
        foreach ($weights as $key => $weight) {
            $currentWeight += $weight;
            if ($random <= $currentWeight) {
                return $key;
            }
        }

        return array_key_first($weights);
    }

    /**
     * Generate context based on mentor's company and department.
     */
    private function generateContext(object $faker, Mentor $mentor): string
    {
        $contexts = [
            "Dans le cadre du développement de l'activité de {$mentor->getCompanyName()}, cette mission s'inscrit dans la stratégie de croissance du département {$mentor->getPosition()}.",
            "Pour répondre aux enjeux actuels de {$mentor->getCompanyName()}, cette mission vise à renforcer les compétences de l'alternant tout en contribuant aux objectifs opérationnels.",
            "En lien avec les projets en cours au sein de {$mentor->getCompanyName()}, cette mission permettra à l'alternant de découvrir les métiers et de développer son expertise.",
            "Cette mission s'intègre dans la politique de formation et d'accompagnement des jeunes talents de {$mentor->getCompanyName()}.",
        ];

        return $faker->randomElement($contexts);
    }

    /**
     * Generate prerequisites based on complexity and term.
     */
    private function generatePrerequisites(object $faker, string $complexity, string $term): array
    {
        $basePrerequisites = [
            'debutant' => [
                'Motivation et curiosité pour le domaine',
                'Capacité d\'adaptation',
                'Sens du travail en équipe',
            ],
            'intermediaire' => [
                'Expérience préalable dans le domaine',
                'Autonomie dans les tâches courantes',
                'Capacité de prise d\'initiative',
                'Compétences de base validées',
            ],
            'avance' => [
                'Expertise technique confirmée',
                'Expérience de management ou de projet',
                'Capacité d\'analyse et de synthèse',
                'Leadership et capacité d\'influence',
            ],
        ];

        $termPrerequisites = [
            'court' => ['Disponibilité immédiate'],
            'moyen' => ['Engagement sur la durée', 'Capacité de montée en compétences'],
            'long' => ['Vision long terme', 'Capacité d\'évolution', 'Engagement fort'],
        ];

        $prerequisites = array_merge(
            $faker->randomElements($basePrerequisites[$complexity], $faker->numberBetween(2, 3)),
            $termPrerequisites[$term],
        );

        return array_values(array_unique($prerequisites));
    }

    /**
     * Get next order index for given term and complexity.
     */
    private function getNextOrderIndex(array $createdMissions, string $term, string $complexity): int
    {
        $maxOrder = 0;

        foreach ($createdMissions as $mission) {
            if ($mission->getTerm() === $term && $mission->getComplexity() === $complexity) {
                $maxOrder = max($maxOrder, $mission->getOrderIndex());
            }
        }

        return $maxOrder + 1;
    }
}
