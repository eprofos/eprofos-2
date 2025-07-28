<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Alternance\AlternanceCalendar;
use App\Entity\Alternance\AlternanceContract;
use App\Entity\User\Student;
use DateInterval;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

/**
 * Fixtures for AlternanceCalendar entity.
 *
 * Creates realistic calendar planning data for alternance students
 * with various rhythms, activities, and scheduling scenarios.
 */
class AlternanceCalendarFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // Get all alternance contracts
        $contracts = $manager->getRepository(AlternanceContract::class)->findAll();

        if (empty($contracts)) {
            echo '⚠️  No alternance contracts found. Please run AlternanceFixtures first.
';

            return;
        }

        // Track processed student+week+year combinations to avoid duplicates
        $processedEntries = [];

        foreach ($contracts as $contract) {
            $this->createCalendarForContract($manager, $faker, $contract, $processedEntries);
        }

        $manager->flush();

        echo '✅ Alternance Calendar: Created calendar entries for ' . count($contracts) . ' contracts
';
    }

    public function getDependencies(): array
    {
        return [
            AlternanceFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['alternance'];
    }

    private function createCalendarForContract(ObjectManager $manager, $faker, AlternanceContract $contract, array &$processedEntries): void
    {
        $student = $contract->getStudent();
        $startDate = $contract->getStartDate();
        $endDate = $contract->getEndDate();

        if (!$startDate || !$endDate) {
            return;
        }

        // Choose a rhythm pattern
        $rhythmPatterns = [
            'alternating' => ['center', 'company'], // 1 week / 1 week
            'two_by_two' => ['center', 'center', 'company', 'company'], // 2 weeks / 2 weeks
            'three_one' => ['center', 'center', 'center', 'company'], // 3 weeks / 1 week
            'custom' => $this->generateCustomPattern($faker),
        ];

        $rhythmKey = $faker->randomElement(array_keys($rhythmPatterns));
        $pattern = $rhythmPatterns[$rhythmKey];

        // Generate calendar entries
        $currentDate = clone $startDate;
        $patternIndex = 0;
        $entryCount = 0;

        while ($currentDate <= $endDate && $entryCount < 52) { // Max 52 weeks
            $week = (int) $currentDate->format('W'); // Use actual ISO week number
            $year = (int) $currentDate->format('Y');

            // Create unique key for this student+week+year combination
            $entryKey = $student->getId() . '_' . $week . '_' . $year;

            // Check if this student already has an entry for this week/year
            if (isset($processedEntries[$entryKey])) {
                // Skip this week as student already has a calendar entry
                $currentDate = new DateTime($currentDate->format('Y-m-d'));
                $currentDate->modify('+7 days');
                $entryCount++;

                continue;
            }

            // Mark this combination as processed
            $processedEntries[$entryKey] = true;

            $location = $pattern[$patternIndex % count($pattern)];

            $calendar = new AlternanceCalendar();
            $calendar->setStudent($student)
                ->setContract($contract)
                ->setWeek($week)
                ->setYear($year)
                ->setLocation($location)
                ->setIsConfirmed($faker->boolean(85)) // 85% confirmed
                ->setModifiedBy($faker->randomElement(['admin', 'mentor', 'teacher']))
            ;

            // Add activities based on location
            $this->addActivitiesToCalendar($calendar, $faker, $location);

            // Add holidays occasionally
            if ($faker->boolean(15)) { // 15% chance of holidays
                $this->addHolidaysToCalendar($calendar, $faker, $week, $year);
            }

            // Add notes occasionally
            if ($faker->boolean(30)) { // 30% chance of notes
                $calendar->setNotes($this->generateRandomNote($faker, $location));
            }

            // Manually set timestamps since Gedmo might not work in fixtures
            $calendar->setCreatedAt(new DateTime());
            $calendar->setUpdatedAt(new DateTime());

            $manager->persist($calendar);

            $currentDate = new DateTime($currentDate->format('Y-m-d'));
            $currentDate->modify('+7 days'); // Add 1 week
            $patternIndex++;
            $entryCount++;
        }
    }

    private function generateCustomPattern($faker): array
    {
        $patterns = [
            ['center', 'company', 'center', 'company', 'company'], // Irregular pattern
            ['center', 'center', 'company', 'center', 'company'], // Mixed pattern
            ['company', 'center', 'center', 'company', 'center'], // Company start
        ];

        return $faker->randomElement($patterns);
    }

    private function addActivitiesToCalendar(AlternanceCalendar $calendar, $faker, string $location): void
    {
        if ($location === 'center') {
            $this->addCenterActivities($calendar, $faker);
        } else {
            $this->addCompanyActivities($calendar, $faker);
        }

        // Add evaluations occasionally
        if ($faker->boolean(20)) { // 20% chance of evaluation
            $this->addEvaluations($calendar, $faker);
        }

        // Add meetings occasionally
        if ($faker->boolean(15)) { // 15% chance of meeting
            $this->addMeetings($calendar, $faker);
        }
    }

    private function addCenterActivities(AlternanceCalendar $calendar, $faker): void
    {
        $sessions = [];

        // Typical center week schedule
        $centerActivities = [
            'Cours théoriques',
            'Travaux pratiques',
            'Projet tutoré',
            'Évaluation continue',
            'Séminaire professionnel',
            'Atelier compétences',
            'Conférence métier',
        ];

        $sessionCount = $faker->numberBetween(3, 6);
        for ($i = 0; $i < $sessionCount; $i++) {
            $sessions[] = [
                'title' => $faker->randomElement($centerActivities),
                'day' => $faker->numberBetween(1, 5), // Monday to Friday
                'time' => $faker->randomElement(['08:30', '10:30', '14:00', '15:30']),
                'duration' => $faker->randomElement([90, 120, 180]), // minutes
                'room' => 'Salle ' . $faker->randomElement(['A101', 'B203', 'C305', 'Lab1', 'Lab2']),
                'instructor' => $faker->name(),
            ];
        }

        $calendar->setCenterSessions($sessions);
    }

    private function addCompanyActivities(AlternanceCalendar $calendar, $faker): void
    {
        $activities = [];

        // Typical company activities
        $companyActivities = [
            'Formation interne',
            'Projet client',
            'Réunion équipe',
            'Accompagnement mentor',
            'Travail autonome',
            'Formation produit',
            'Analyse des besoins',
            'Développement solution',
            'Tests et validation',
            'Présentation résultats',
        ];

        $activityCount = $faker->numberBetween(2, 5);
        for ($i = 0; $i < $activityCount; $i++) {
            $activities[] = [
                'title' => $faker->randomElement($companyActivities),
                'day' => $faker->numberBetween(1, 5),
                'time' => $faker->randomElement(['09:00', '10:00', '14:00', '15:00', '16:00']),
                'duration' => $faker->randomElement([60, 120, 240, 480]), // minutes
                'location' => $faker->randomElement(['Bureau', 'Salle de réunion', 'Atelier', 'Terrain', 'Client']),
                'supervisor' => $faker->name(),
            ];
        }

        $calendar->setCompanyActivities($activities);
    }

    private function addEvaluations(AlternanceCalendar $calendar, $faker): void
    {
        $evaluations = [];

        $evaluationTypes = [
            'Évaluation périodique',
            'Contrôle continu',
            'Présentation projet',
            'Évaluation compétences',
            'Bilan de période',
            'Auto-évaluation',
            'Évaluation 360°',
        ];

        $evalCount = $faker->numberBetween(1, 2);
        for ($i = 0; $i < $evalCount; $i++) {
            $evaluations[] = [
                'type' => $faker->randomElement($evaluationTypes),
                'date' => $faker->dateTimeBetween('monday this week', 'friday this week')->format('Y-m-d'),
                'time' => $faker->randomElement(['09:00', '10:30', '14:00', '15:30']),
                'duration' => $faker->randomElement([30, 60, 90, 120]),
                'evaluator' => $faker->name(),
                'criteria' => $faker->randomElements([
                    'Compétences techniques',
                    'Autonomie',
                    'Communication',
                    'Initiative',
                    'Travail en équipe',
                    'Respect des délais',
                    'Qualité du travail',
                ], $faker->numberBetween(2, 4)),
            ];
        }

        $calendar->setEvaluations($evaluations);
    }

    private function addMeetings(AlternanceCalendar $calendar, $faker): void
    {
        $meetings = [];

        $meetingTypes = [
            'Point avec le mentor',
            'Réunion de coordination',
            'Entretien pédagogique',
            'Bilan intermédiaire',
            'Point d\'avancement',
            'Réunion tripartite',
            'Suivi individuel',
        ];

        $meetingCount = $faker->numberBetween(1, 2);
        for ($i = 0; $i < $meetingCount; $i++) {
            $meetings[] = [
                'type' => $faker->randomElement($meetingTypes),
                'date' => $faker->dateTimeBetween('monday this week', 'friday this week')->format('Y-m-d'),
                'time' => $faker->randomElement(['08:30', '12:00', '17:00', '17:30']),
                'duration' => $faker->randomElement([30, 45, 60]),
                'location' => $faker->randomElement(['Bureau', 'Visioconférence', 'Centre formation', 'Entreprise']),
                'participants' => $faker->randomElements([
                    'Alternant',
                    'Mentor entreprise',
                    'Tuteur pédagogique',
                    'Responsable formation',
                    'RH',
                ], $faker->numberBetween(2, 3)),
                'objectives' => $faker->randomElements([
                    'Faire le point sur les missions',
                    'Évaluer la progression',
                    'Identifier les difficultés',
                    'Planifier les prochaines étapes',
                    'Ajuster les objectifs',
                ], $faker->numberBetween(1, 3)),
            ];
        }

        $calendar->setMeetings($meetings);
    }

    private function addHolidaysToCalendar(AlternanceCalendar $calendar, $faker, int $week, int $year): void
    {
        $holidays = [];

        // French holidays that might occur
        $possibleHolidays = [
            ['name' => 'Jour de l\'An', 'date' => "{$year}-01-01"],
            ['name' => 'Fête du Travail', 'date' => "{$year}-05-01"],
            ['name' => 'Fête de la Victoire', 'date' => "{$year}-05-08"],
            ['name' => 'Fête Nationale', 'date' => "{$year}-07-14"],
            ['name' => 'Assomption', 'date' => "{$year}-08-15"],
            ['name' => 'Toussaint', 'date' => "{$year}-11-01"],
            ['name' => 'Armistice', 'date' => "{$year}-11-11"],
            ['name' => 'Noël', 'date' => "{$year}-12-25"],
        ];

        // Check if any holiday falls in this week
        $weekStart = new DateTime();
        $weekStart->setISODate($year, $week);
        $weekEnd = clone $weekStart;
        $weekEnd->add(new DateInterval('P6D'));

        foreach ($possibleHolidays as $holiday) {
            $holidayDate = new DateTime($holiday['date']);
            if ($holidayDate >= $weekStart && $holidayDate <= $weekEnd) {
                $holidays[] = [
                    'name' => $holiday['name'],
                    'date' => $holiday['date'],
                    'type' => 'public_holiday',
                ];
            }
        }

        // Sometimes add custom holidays/events
        if (empty($holidays) && $faker->boolean(30)) {
            $customEvents = [
                'Pont',
                'Congés entreprise',
                'Formation exceptionnelle',
                'Événement spécial',
                'Maintenance système',
            ];

            $holidays[] = [
                'name' => $faker->randomElement($customEvents),
                'date' => $faker->dateTimeBetween($weekStart, $weekEnd)->format('Y-m-d'),
                'type' => 'custom',
            ];
        }

        if (!empty($holidays)) {
            $calendar->setHolidays($holidays);
        }
    }

    private function generateRandomNote($faker, string $location): string
    {
        if ($location === 'center') {
            $notes = [
                'Semaine intensive de formation théorique',
                'Projet de groupe prévu cette semaine',
                'Évaluation finale du module',
                'Présentation des projets étudiants',
                'Semaine portes ouvertes - planning adapté',
                'Formation avec intervenant externe',
                'Atelier pratique renforcé',
            ];
        } else {
            $notes = [
                'Mission prioritaire à terminer',
                'Formation produit avec l\'équipe',
                'Présentation client en fin de semaine',
                'Accompagnement renforcé du mentor',
                'Participation au projet stratégique',
                'Audit qualité prévu',
                'Déplacement client possible',
            ];
        }

        return $faker->randomElement($notes);
    }
}
