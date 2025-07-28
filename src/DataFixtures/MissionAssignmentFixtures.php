<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Alternance\CompanyMission;
use App\Entity\Alternance\MissionAssignment;
use App\Entity\User\Mentor;
use App\Entity\User\Student;
use DateTime;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class MissionAssignmentFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // Get students and missions
        $students = $manager->getRepository(Student::class)->findAll();
        $missions = $manager->getRepository(CompanyMission::class)->findAll();

        if (empty($students)) {
            return;
        }

        if (empty($missions)) {
            return;
        }

        // Ensure mentor@eprofos.fr gets assignments by creating specific assignments for this mentor's missions
        $mentorEprofos = $manager->getRepository(Mentor::class)->findOneBy(['email' => 'mentor@eprofos.fr']);
        if ($mentorEprofos) {
            $this->createAssignmentsForSpecificMentor($manager, $mentorEprofos, $students, $faker);
        }

        $assignmentCount = 0;
        $statusDistribution = [
            'planifiee' => 25,    // 25% nouvelles affectations
            'en_cours' => 35,     // 35% en cours
            'terminee' => 30,     // 30% terminées
            'suspendue' => 10,     // 10% suspendues
        ];

        foreach ($students as $student) {
            // Each student gets 2-8 mission assignments with progression logic
            $assignmentsToCreate = $faker->numberBetween(2, 8);

            // Track student's mission progression
            $studentProgressions = [
                'debutant' => ['court' => 0, 'moyen' => 0, 'long' => 0],
                'intermediaire' => ['court' => 0, 'moyen' => 0, 'long' => 0],
                'avance' => ['court' => 0, 'moyen' => 0, 'long' => 0],
            ];

            $studentAssignments = [];

            // Randomly select missions following progression logic
            $availableMissions = $this->getProgressiveMissions($missions, $assignmentsToCreate);

            for ($i = 0; $i < $assignmentsToCreate; $i++) {
                if (empty($availableMissions)) {
                    break;
                }

                $mission = array_shift($availableMissions);

                $assignment = new MissionAssignment();
                $assignment->setMission($mission);
                $assignment->setStudent($student);

                // Set assignment date (missions are assigned over time)
                $startDate = $faker->dateTimeBetween('-12 months', 'now');
                $assignment->setStartDate($startDate);

                // Set end date based on mission duration
                $expectedDays = $this->getExpectedDurationDays($mission->getDuration());
                $endDate = (clone $startDate)->modify("+{$expectedDays} days");
                $assignment->setEndDate($endDate);

                // Determine status based on assignment date and distribution
                $status = $this->selectWeightedRandom($statusDistribution);

                // Adjust status based on assignment date (older missions more likely to be completed)
                $daysSinceAssignment = $startDate->diff(new DateTimeImmutable())->days;
                if ($daysSinceAssignment > 90 && $faker->boolean(70)) {
                    $status = 'terminee';
                } elseif ($daysSinceAssignment < 7 && $faker->boolean(60)) {
                    $status = 'planifiee';
                }

                $assignment->setStatus($status);

                // Set dates and progress based on status
                $this->setAssignmentDetails($assignment, $status, $startDate, $faker);

                // Set evaluations
                $this->setEvaluations($assignment, $status, $faker);

                $manager->persist($assignment);
                $studentAssignments[] = $assignment;
                $assignmentCount++;

                // Update progression tracking
                $complexity = $mission->getComplexity();
                $term = $mission->getTerm();
                $studentProgressions[$complexity][$term]++;
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            CompanyMissionFixtures::class,
            StudentFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['alternance', 'missions'];
    }

    /**
     * Create specific assignments for a mentor to ensure they have data to display.
     *
     * @param mixed $mentor
     */
    private function createAssignmentsForSpecificMentor(ObjectManager $manager, $mentor, array $students, object $faker): void
    {
        // Get all missions for this mentor
        $mentorMissions = $manager->getRepository(CompanyMission::class)->findBy(['supervisor' => $mentor]);

        if (empty($mentorMissions)) {
            return;
        }

        // Assign each mission to 1-3 students with different statuses
        foreach ($mentorMissions as $mission) {
            $assignmentsForThisMission = $faker->numberBetween(1, 3);
            $selectedStudents = $faker->randomElements($students, min($assignmentsForThisMission, count($students)));

            foreach ($selectedStudents as $student) {
                $assignment = new MissionAssignment();
                $assignment->setMission($mission);
                $assignment->setStudent($student);

                // Set assignment date (varied timeframes)
                $startDate = $faker->dateTimeBetween('-6 months', '-1 week');
                $assignment->setStartDate($startDate);

                // Set end date based on mission duration
                $expectedDays = $this->getExpectedDurationDays($mission->getDuration());
                $endDate = (clone $startDate)->modify("+{$expectedDays} days");
                $assignment->setEndDate($endDate);

                // Assign realistic status based on timing
                $daysSinceStart = $startDate->diff(new DateTimeImmutable())->days;
                if ($daysSinceStart > $expectedDays + 7) {
                    $status = $faker->randomElement(['terminee', 'suspendue']);
                } elseif ($daysSinceStart > 7) {
                    $status = $faker->randomElement(['en_cours', 'terminee']);
                } else {
                    $status = 'planifiee';
                }

                $assignment->setStatus($status);

                // Set assignment details based on status
                $this->setAssignmentDetails($assignment, $status, $startDate, $faker);

                // Set evaluations
                $this->setEvaluations($assignment, $status, $faker);

                $manager->persist($assignment);
            }
        }

        $manager->flush();
    }

    /**
     * Get missions in progressive order for a student.
     */
    private function getProgressiveMissions(array $missions, int $count): array
    {
        $faker = Factory::create('fr_FR');

        // Group missions by complexity and term
        $groupedMissions = [];
        foreach ($missions as $mission) {
            $groupedMissions[$mission->getComplexity()][$mission->getTerm()][] = $mission;
        }

        // Sort within each group by order index
        foreach ($groupedMissions as $complexity => $terms) {
            foreach ($terms as $term => $missionList) {
                usort($missionList, static fn ($a, $b) => $a->getOrderIndex() <=> $b->getOrderIndex());
                $groupedMissions[$complexity][$term] = $missionList;
            }
        }

        $selectedMissions = [];
        $complexityProgression = ['debutant', 'intermediaire', 'avance'];
        $termProgression = ['court', 'moyen', 'long'];

        // Start with easier missions and progress
        for ($i = 0; $i < $count; $i++) {
            // Determine complexity level based on progression
            $complexityIndex = min(2, (int) ($i / 3)); // Every 3 missions, increase complexity
            $complexity = $complexityProgression[$complexityIndex];

            // Determine term based on student advancement
            $termIndex = min(2, (int) ($i / 2)); // Every 2 missions, increase term length
            $term = $termProgression[$termIndex];

            // Add some randomness
            if ($faker->boolean(20) && $complexityIndex > 0) {
                $complexity = $complexityProgression[$complexityIndex - 1];
            }
            if ($faker->boolean(20) && $termIndex > 0) {
                $term = $termProgression[$termIndex - 1];
            }

            // Select mission from appropriate group
            if (isset($groupedMissions[$complexity][$term]) && !empty($groupedMissions[$complexity][$term])) {
                $mission = array_shift($groupedMissions[$complexity][$term]);
                $selectedMissions[] = $mission;
            } elseif (!empty($missions)) {
                // Fallback to any available mission
                $mission = $faker->randomElement($missions);
                $selectedMissions[] = $mission;
                // Remove from array to avoid duplicates
                $missions = array_filter($missions, static fn ($m) => $m->getId() !== $mission->getId());
            }
        }

        return $selectedMissions;
    }

    /**
     * Set assignment details based on status.
     */
    private function setAssignmentDetails(MissionAssignment $assignment, string $status, DateTime $startDate, object $faker): void
    {
        switch ($status) {
            case 'planifiee':
                // Just assigned, no progress yet
                $assignment->setCompletionRate(0.0);

                // Generate some initial intermediate objectives
                $objectives = $this->generateIntermediateObjectives($assignment->getMission(), $faker);
                $assignment->setIntermediateObjectives($objectives);
                break;

            case 'en_cours':
                $assignment->setCompletionRate($faker->numberBetween(10, 85));

                // Generate intermediate objectives with some completed
                $objectives = $this->generateIntermediateObjectives($assignment->getMission(), $faker, true);
                $assignment->setIntermediateObjectives($objectives);

                // Add some achievements and difficulties
                $achievements = $this->generateAchievements($faker);
                $assignment->setAchievements($achievements);

                $difficulties = $this->generateDifficulties($faker);
                $assignment->setDifficulties($difficulties);
                break;

            case 'terminee':
                $assignment->setCompletionRate(100.0);

                // All objectives completed
                $objectives = $this->generateIntermediateObjectives($assignment->getMission(), $faker, true, true);
                $assignment->setIntermediateObjectives($objectives);

                // Full achievements list
                $achievements = $this->generateAchievements($faker, true);
                $assignment->setAchievements($achievements);

                // Some difficulties overcome
                $difficulties = $this->generateDifficulties($faker, false);
                $assignment->setDifficulties($difficulties);
                break;

            case 'suspendue':
                $assignment->setCompletionRate($faker->numberBetween(15, 60));

                // Some objectives completed
                $objectives = $this->generateIntermediateObjectives($assignment->getMission(), $faker, true);
                $assignment->setIntermediateObjectives($objectives);

                // Add difficulties that led to suspension
                $difficulties = $this->generateDifficulties($faker, true);
                $assignment->setDifficulties($difficulties);
                break;
        }
    }

    /**
     * Set evaluations based on status.
     */
    private function setEvaluations(MissionAssignment $assignment, string $status, object $faker): void
    {
        // Student evaluation (always present for en_cours and terminee)
        if (in_array($status, ['en_cours', 'terminee', 'suspendue'], true)) {
            $assignment->setStudentSatisfaction($faker->numberBetween(6, 10)); // Scale 1-10

            $studentComments = [
                'Mission très enrichissante qui m\'a permis de développer mes compétences',
                'Bonne expérience, j\'ai appris beaucoup sur le métier',
                'Mission bien encadrée avec un bon accompagnement',
                'Objectifs clairs, équipe accueillante',
                'Parfait pour découvrir les enjeux de l\'entreprise',
                'Mission stimulante avec de vrais défis à relever',
            ];
            $assignment->setStudentFeedback($faker->randomElement($studentComments));
        }

        // Mentor evaluation (present for completed missions)
        if ($status === 'terminee') {
            $assignment->setMentorRating($faker->numberBetween(6, 10)); // Scale 1-10

            $mentorComments = [
                'Étudiant motivé et impliqué, résultats conformes aux attentes',
                'Très bonne progression, autonomie acquise rapidement',
                'Travail de qualité, esprit d\'équipe excellent',
                'Bon niveau technique, capacité d\'adaptation remarquable',
                'Étudiant sérieux et professionnel, recommandé pour missions avancées',
                'Performance satisfaisante, objectifs atteints dans les délais',
            ];
            $assignment->setMentorFeedback($faker->randomElement($mentorComments));

            // Competencies acquired (subset of mission's skills to acquire)
            $missionSkills = $assignment->getMission()->getSkillsToAcquire();
            $acquiredSkills = $faker->randomElements(
                $missionSkills,
                $faker->numberBetween(
                    max(1, (int) (count($missionSkills) * 0.6)),
                    count($missionSkills),
                ),
            );
            $assignment->setCompetenciesAcquired($acquiredSkills);
        }
    }

    /**
     * Convert duration string to expected days.
     */
    private function getExpectedDurationDays(string $duration): int
    {
        $durationMap = [
            '1-2 semaines' => 10,
            '3-4 semaines' => 25,
            '1 mois' => 30,
            '1-2 mois' => 45,
            '2-3 mois' => 75,
            '3-4 mois' => 105,
            '4-6 mois' => 150,
            '6 mois' => 180,
            '1 an' => 365,
        ];

        return $durationMap[$duration] ?? 30;
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
     * Generate intermediate objectives for a mission.
     */
    private function generateIntermediateObjectives(CompanyMission $mission, object $faker, bool $withProgress = false, bool $allCompleted = false): array
    {
        $missionObjectives = $mission->getObjectives();

        // Generate structured objectives with completion tracking
        $objectives = [];

        foreach ($missionObjectives as $objective) {
            $objectiveData = [
                'title' => $objective,
                'description' => 'Objectif issu de la mission: ' . $objective,
                'completed' => $allCompleted ? true : ($withProgress ? $faker->boolean(60) : false),
                'completion_date' => null,
            ];

            if ($objectiveData['completed']) {
                $objectiveData['completion_date'] = $faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d H:i:s');
            }

            $objectives[] = $objectiveData;
        }

        // Add some additional intermediate objectives
        $additionalObjectiveTemplates = [
            [
                'title' => 'Se familiariser avec l\'environnement de travail',
                'description' => 'Découvrir l\'organisation, les locaux et les équipes',
            ],
            [
                'title' => 'Maîtriser les outils et procédures',
                'description' => 'Apprendre à utiliser les outils métier et comprendre les processus',
            ],
            [
                'title' => 'Développer l\'autonomie sur les tâches courantes',
                'description' => 'Être capable de réaliser les tâches quotidiennes sans supervision',
            ],
            [
                'title' => 'Produire des livrables de qualité',
                'description' => 'Respecter les standards de qualité et les délais impartis',
            ],
            [
                'title' => 'Collaborer efficacement avec l\'équipe',
                'description' => 'S\'intégrer dans l\'équipe et communiquer efficacement',
            ],
        ];

        // Add 1-3 additional objectives randomly
        $extraObjectives = $faker->randomElements($additionalObjectiveTemplates, $faker->numberBetween(1, 3));

        foreach ($extraObjectives as $template) {
            $objectiveData = [
                'title' => $template['title'],
                'description' => $template['description'],
                'completed' => $allCompleted ? true : ($withProgress ? $faker->boolean(40) : false),
                'completion_date' => null,
            ];

            if ($objectiveData['completed']) {
                $objectiveData['completion_date'] = $faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d H:i:s');
            }

            $objectives[] = $objectiveData;
        }

        return $objectives;
    }

    /**
     * Generate achievements for a mission.
     */
    private function generateAchievements(object $faker, bool $extensive = false): array
    {
        $baseAchievements = [
            'Intégration réussie dans l\'équipe',
            'Maîtrise des outils de travail',
            'Autonomie progressive acquise',
            'Respect des délais fixés',
            'Qualité du travail reconnue',
        ];

        $advancedAchievements = [
            'Proposition d\'améliorations adoptées',
            'Formation d\'autres collaborateurs',
            'Prise d\'initiatives valorisées',
            'Résolution de problèmes complexes',
            'Contribution majeure aux projets',
        ];

        $achievements = $faker->randomElements($baseAchievements, $faker->numberBetween(2, 4));

        if ($extensive) {
            $additionalAchievements = $faker->randomElements($advancedAchievements, $faker->numberBetween(1, 3));
            $achievements = array_merge($achievements, $additionalAchievements);
        }

        return array_values(array_unique($achievements));
    }

    /**
     * Generate difficulties for a mission.
     */
    private function generateDifficulties(object $faker, bool $serious = false): array
    {
        $minorDifficulties = [
            'Adaptation aux outils spécifiques',
            'Compréhension des processus internes',
            'Gestion du temps initial',
            'Communication avec certains interlocuteurs',
        ];

        $majorDifficulties = [
            'Complexité technique sous-estimée',
            'Manque de ressources disponibles',
            'Changements fréquents des priorités',
            'Problèmes de coordination équipe',
            'Difficultés relationnelles',
        ];

        if ($serious) {
            return $faker->randomElements($majorDifficulties, $faker->numberBetween(1, 3));
        }

        return $faker->randomElements($minorDifficulties, $faker->numberBetween(0, 2));
    }
}
