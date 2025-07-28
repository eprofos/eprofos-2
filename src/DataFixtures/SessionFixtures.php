<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Training\Formation;
use App\Entity\Training\Session;
use App\Entity\Training\SessionRegistration;
use App\Service\CRM\ProspectManagementService;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Exception;
use Faker\Factory;
use Psr\Log\LoggerInterface;

class SessionFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function __construct(
        private ProspectManagementService $prospectService,
        private LoggerInterface $logger,
    ) {}

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // Get all formations
        $formations = $manager->getRepository(Formation::class)->findAll();

        if (empty($formations)) {
            return;
        }

        $sessionCount = 0;
        $registrationCount = 0;
        $alternanceSessionCount = 0;

        foreach ($formations as $formation) {
            // Create 2-4 regular sessions per formation
            $sessionsToCreate = $faker->numberBetween(2, 4);

            // Determine if this formation should have alternance sessions
            // Focus on long formations (> 100 hours) and technical domains
            $shouldHaveAlternance = $this->shouldCreateAlternanceSession($formation, $faker);

            // Create regular sessions
            for ($i = 0; $i < $sessionsToCreate; $i++) {
                $session = new Session();

                // Session name
                $sessionNames = [
                    'Session du matin',
                    'Session de l\'après-midi',
                    'Session intensive',
                    'Session en soirée',
                    'Session weekend',
                    'Session inter-entreprises',
                    'Session intra-entreprise',
                ];
                $session->setName($faker->randomElement($sessionNames));

                // Dates - some in the past, some upcoming
                $isUpcoming = $faker->boolean(70); // 70% chance of upcoming session

                if ($isUpcoming) {
                    // Future sessions
                    $startDate = $faker->dateTimeBetween('+1 week', '+6 months');
                } else {
                    // Past sessions
                    $startDate = $faker->dateTimeBetween('-6 months', '-1 week');
                }

                $endDate = clone $startDate;
                $duration = $formation->getDurationHours() ?: $faker->numberBetween(7, 35);
                $endDate->modify("+{$duration} hours");

                $session->setStartDate($startDate);
                $session->setEndDate($endDate);

                // Location
                $locations = [
                    'Paris - Centre de formation EPROFOS',
                    'Lyon - Espace de coworking Tech',
                    'Marseille - Centre d\'affaires Méditerranée',
                    'Toulouse - Campus numérique',
                    'En ligne - Plateforme EPROFOS',
                    'Bordeaux - Hub innovation',
                    'Lille - Centre de formation Nord',
                    'Nantes - Espace formation Atlantique',
                ];
                $session->setLocation($faker->randomElement($locations));

                // Capacity
                $maxCapacity = $faker->numberBetween(8, 20);
                $session->setMaxCapacity($maxCapacity);

                // Status based on date and capacity
                if ($isUpcoming) {
                    $statuses = ['open', 'full', 'cancelled'];
                    $weights = [70, 25, 5]; // 70% open, 25% full, 5% cancelled
                    $status = $faker->randomElement(array_merge(
                        array_fill(0, $weights[0], $statuses[0]),
                        array_fill(0, $weights[1], $statuses[1]),
                        array_fill(0, $weights[2], $statuses[2]),
                    ));
                } else {
                    $statuses = ['completed', 'cancelled'];
                    $weights = [90, 10]; // 90% completed, 10% cancelled
                    $status = $faker->randomElement(array_merge(
                        array_fill(0, $weights[0], $statuses[0]),
                        array_fill(0, $weights[1], $statuses[1]),
                    ));
                }
                $session->setStatus($status);

                // Current registrations based on status
                if ($status === 'full') {
                    $currentRegistrations = $maxCapacity;
                } elseif ($status === 'cancelled') {
                    $currentRegistrations = 0;
                } elseif ($status === 'completed') {
                    $currentRegistrations = $faker->numberBetween($maxCapacity - 3, $maxCapacity);
                } else { // open
                    $currentRegistrations = $faker->numberBetween(0, $maxCapacity - 1);
                }
                $session->setCurrentRegistrations($currentRegistrations);

                // Price (sometimes different from formation price)
                if ($faker->boolean(30)) { // 30% chance of different price
                    $session->setPrice((string)$faker->randomFloat(2, 300, 2000));
                }

                // Description
                $descriptions = [
                    'Session pratique avec études de cas réels et mise en situation.',
                    'Formation intensive avec accompagnement personnalisé.',
                    'Session inter-entreprises favorisant les échanges d\'expériences.',
                    'Formation en petit groupe pour un accompagnement optimal.',
                    'Session avec certification à l\'issue de la formation.',
                    'Formation avec support pédagogique complet et exercices pratiques.',
                ];
                $session->setDescription($faker->randomElement($descriptions));

                // Notes for admin
                if ($faker->boolean(40)) {
                    $notes = [
                        'Matériel informatique fourni',
                        'Pause-café incluse',
                        'Parking disponible sur site',
                        'Formation éligible CPF',
                        'Support de cours en version numérique',
                        'Attestation de formation remise en fin de session',
                    ];
                    $session->setNotes($faker->randomElement($notes));
                }

                // Link to formation
                $session->setFormation($formation);

                $manager->persist($session);
                $sessionCount++;

                // Create some registrations for this session
                if ($currentRegistrations > 0 && in_array($status, ['open', 'full', 'completed'], true)) {
                    for ($j = 0; $j < $currentRegistrations; $j++) {
                        $registration = new SessionRegistration();

                        $registration->setFirstName($faker->firstName);
                        $registration->setLastName($faker->lastName);
                        $registration->setEmail($faker->unique()->email);
                        $registration->setPhone($faker->phoneNumber);

                        if ($faker->boolean(80)) { // 80% have company
                            $registration->setCompany($faker->company);
                            $registration->setPosition($faker->jobTitle);
                        }

                        if ($faker->boolean(30)) { // 30% have notes
                            $notes = [
                                'Besoin d\'un accompagnement spécifique',
                                'Personne en situation de handicap',
                                'Régime alimentaire particulier',
                                'Première formation dans ce domaine',
                                'Formation dans le cadre d\'une reconversion',
                            ];
                            $registration->setNotes($faker->randomElement($notes));
                        }

                        // Registration date
                        $registrationDate = $faker->dateTimeBetween($startDate->format('Y-m-d') . ' -2 months', $startDate->format('Y-m-d') . ' -1 day');
                        $registration->setCreatedAt(DateTimeImmutable::createFromMutable($registrationDate));
                        $registration->setUpdatedAt(DateTimeImmutable::createFromMutable($registrationDate));

                        // Status
                        if ($status === 'completed') {
                            $regStatuses = ['confirmed', 'attended', 'no_show'];
                            $regWeights = [10, 80, 10]; // Most attended
                            $regStatus = $faker->randomElement(array_merge(
                                array_fill(0, $regWeights[0], $regStatuses[0]),
                                array_fill(0, $regWeights[1], $regStatuses[1]),
                                array_fill(0, $regWeights[2], $regStatuses[2]),
                            ));
                        } else {
                            $regStatuses = ['pending', 'confirmed', 'cancelled'];
                            $regWeights = [20, 70, 10]; // Most confirmed
                            $regStatus = $faker->randomElement(array_merge(
                                array_fill(0, $regWeights[0], $regStatuses[0]),
                                array_fill(0, $regWeights[1], $regStatuses[1]),
                                array_fill(0, $regWeights[2], $regStatuses[2]),
                            ));
                        }
                        $registration->setStatus($regStatus);

                        // Cancellation date if cancelled
                        if ($regStatus === 'cancelled') {
                            $cancellationDate = $faker->dateTimeBetween($registrationDate, $startDate->format('Y-m-d') . ' -1 day');
                            // Note: No setCancellationDate method available in entity
                        }

                        $registration->setSession($session);

                        $manager->persist($registration);
                        $registrationCount++;
                    }
                }
            }

            // Create alternance sessions if appropriate
            if ($shouldHaveAlternance) {
                $this->createAlternanceSessions($manager, $faker, $formation, $sessionCount, $registrationCount, $alternanceSessionCount);
            }
        }

        $manager->flush();

        // Create prospects from session registrations using the ProspectManagementService
        echo "Creating prospects from session registrations...\n";
        $createdProspects = 0;

        $sessionRegistrations = $manager->getRepository(SessionRegistration::class)->findAll();

        foreach ($sessionRegistrations as $registration) {
            try {
                $prospect = $this->prospectService->createProspectFromSessionRegistration($registration);
                $createdProspects++;

                $this->logger->info('Prospect created from session registration fixture', [
                    'prospect_id' => $prospect->getId(),
                    'session_registration_id' => $registration->getId(),
                    'email' => $registration->getEmail(),
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to create prospect from session registration fixture', [
                    'session_registration_id' => $registration->getId(),
                    'email' => $registration->getEmail(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        echo "✅ Sessions: Created {$sessionCount} sessions with {$registrationCount} registrations\n";
        echo "✅ Alternance: Created {$alternanceSessionCount} alternance sessions\n";
        echo "✅ Prospects: Created {$createdProspects} prospects from session registrations\n";
    }

    public function getDependencies(): array
    {
        return [
            FormationFixtures::class,
            ProspectFixtures::class, // Ensure base prospects are created first
        ];
    }

    public static function getGroups(): array
    {
        return ['session'];
    }

    private function shouldCreateAlternanceSession(Formation $formation, $faker): bool
    {
        // Criteria for alternance sessions:
        // 1. Longer formations (> 35 hours) are more likely
        // 2. Technical formations are preferred
        // 3. Some randomness for variety

        $duration = $formation->getDurationHours() ?: 0;
        $category = $formation->getCategory();

        if ($duration >= 50) {
            return $faker->boolean(70); // 70% chance for longer formations
        }
        if ($duration >= 35) {
            // Check if technical category
            if ($category && in_array(strtolower($category->getName()), ['développement', 'informatique', 'numérique', 'technique'], true)) {
                return $faker->boolean(80); // 80% chance for technical formations
            }

            return $faker->boolean(40); // 40% chance for other medium formations
        }
        if ($duration >= 20) {
            return $faker->boolean(20); // 20% chance for shorter formations
        }

        return $faker->boolean(10); // 10% chance for very short formations
    }

    private function createAlternanceSessions($manager, $faker, Formation $formation, &$sessionCount, &$registrationCount, &$alternanceSessionCount): void
    {
        // Create 1-2 alternance sessions per eligible formation
        $alternanceSessionsToCreate = $faker->numberBetween(1, 2);

        for ($i = 0; $i < $alternanceSessionsToCreate; $i++) {
            $session = new Session();

            // Alternance-specific session names
            $alternanceNames = [
                'Formation en alternance - Promotion ' . $faker->randomElement(['A', 'B', 'C']),
                'Cursus alternance ' . date('Y'),
                'Programme alternance - Rentrée ' . $faker->randomElement(['septembre', 'janvier', 'mars']),
                'Formation professionnalisante en alternance',
                'Parcours alternance certifiant',
            ];
            $session->setName($faker->randomElement($alternanceNames));

            // Alternance sessions are typically longer and future-oriented
            $startDate = $faker->dateTimeBetween('+1 month', '+8 months');
            $endDate = clone $startDate;

            // Alternance durations are typically 6-24 months
            $durationMonths = $faker->numberBetween(6, 24);
            $endDate->modify("+{$durationMonths} months");

            $session->setStartDate($startDate);
            $session->setEndDate($endDate);

            // Alternance-specific locations
            $alternanceLocations = [
                'Paris - Centre EPROFOS + Entreprises partenaires',
                'Lyon - Campus alternance + Structures d\'accueil',
                'Marseille - Centre formation + Entreprises locales',
                'Toulouse - Hub alternance + Partenaires tech',
                'Bordeaux - Campus pro + Entreprises régionales',
                'Lille - Centre alternance + Industries du Nord',
                'Nantes - Formation + Entreprises Atlantique',
                'Mixte - Centre formation + Télétravail entreprise',
            ];
            $session->setLocation($faker->randomElement($alternanceLocations));

            // Smaller capacity for alternance (more personalized)
            $maxCapacity = $faker->numberBetween(6, 15);
            $session->setMaxCapacity($maxCapacity);

            // Alternance sessions are usually open or in preparation
            $alternanceStatuses = ['open', 'preparation', 'full'];
            $alternanceWeights = [60, 30, 10];
            $status = $faker->randomElement(array_merge(
                array_fill(0, $alternanceWeights[0], $alternanceStatuses[0]),
                array_fill(0, $alternanceWeights[1], $alternanceStatuses[1]),
                array_fill(0, $alternanceWeights[2], $alternanceStatuses[2]),
            ));
            $session->setStatus($status);

            // Current registrations
            if ($status === 'full') {
                $currentRegistrations = $maxCapacity;
            } elseif ($status === 'preparation') {
                $currentRegistrations = $faker->numberBetween(0, 3);
            } else { // open
                $currentRegistrations = $faker->numberBetween(0, $maxCapacity - 2);
            }
            $session->setCurrentRegistrations($currentRegistrations);

            // Alternance-specific descriptions
            $alternanceDescriptions = [
                'Formation en alternance alliant théorie en centre et pratique en entreprise. Rythme adapté aux besoins professionnels.',
                'Cursus professionnalisant avec accompagnement personnalisé et suivi en entreprise par un tuteur expérimenté.',
                'Programme alternance certifiant avec évaluation continue et validation des compétences en situation réelle.',
                'Formation diplômante en alternance avec immersion progressive en milieu professionnel.',
                'Parcours alternance sur-mesure avec adaptation du rythme selon les contraintes de l\'entreprise d\'accueil.',
            ];
            $session->setDescription($faker->randomElement($alternanceDescriptions));

            // Alternance-specific notes
            $alternanceNotes = [
                'Recherche d\'entreprise accompagnée par nos conseillers',
                'Suivi personnalisé avec tuteur entreprise et référent pédagogique',
                'Possibilité d\'aménagement du rythme selon besoins entreprise',
                'Certification professionnelle à l\'issue du parcours',
                'Accompagnement à l\'insertion professionnelle',
            ];
            $session->setNotes($faker->randomElement($alternanceNotes));

            // Price usually lower for alternance (funding différent)
            $basePrice = $formation->getPrice() ?: 1500;
            $alternancePrice = $basePrice * $faker->randomFloat(2, 0.3, 0.7); // 30-70% of base price
            $session->setPrice((string)$alternancePrice);

            // Set alternance-specific fields
            $session->setIsAlternanceSession(true);

            // Alternance type
            $alternanceTypes = ['apprentissage', 'professionnalisation', 'stage_alterné'];
            $alternanceTypeWeights = [50, 35, 15]; // Apprentissage most common
            $alternanceType = $faker->randomElement(array_merge(
                array_fill(0, $alternanceTypeWeights[0], $alternanceTypes[0]),
                array_fill(0, $alternanceTypeWeights[1], $alternanceTypes[1]),
                array_fill(0, $alternanceTypeWeights[2], $alternanceTypes[2]),
            ));
            $session->setAlternanceType($alternanceType);

            // Duration in months -> convert to weeks for the entity
            $durationWeeks = $durationMonths * 4; // Approximate conversion
            $session->setMinimumAlternanceDuration($durationWeeks);

            // Realistic percentages based on alternance type
            if ($alternanceType === 'apprentissage') {
                // Classic apprenticeship: more time in company
                $centerPercentage = $faker->numberBetween(25, 40);
                $companyPercentage = 100 - $centerPercentage;
            } elseif ($alternanceType === 'professionnalisation') {
                // Professional training: balanced approach
                $centerPercentage = $faker->numberBetween(35, 50);
                $companyPercentage = 100 - $centerPercentage;
            } else { // stage_alterné
                // Alternating internship: more flexible
                $centerPercentage = $faker->numberBetween(40, 60);
                $companyPercentage = 100 - $centerPercentage;
            }

            $session->setCenterPercentage($centerPercentage);
            $session->setCompanyPercentage($companyPercentage);

            // Prerequisites for alternance
            $prerequisites = [
                'Niveau Bac+2 minimum ou expérience professionnelle équivalente',
                'Projet professionnel défini dans le secteur d\'activité',
                'Capacité à travailler en autonomie et en équipe',
                'Motivation pour l\'apprentissage en situation professionnelle',
                'Disponibilité pour respecter le rythme alternance centre/entreprise',
                'Niveau requis validé par entretien de motivation',
            ];
            $selectedPrerequisites = $faker->randomElements($prerequisites, $faker->numberBetween(2, 4));
            $session->setAlternancePrerequisites($selectedPrerequisites);

            // Rhythm description
            $rhythms = [
                '2 jours en centre / 3 jours en entreprise par semaine',
                '1 semaine en centre / 3 semaines en entreprise par mois',
                '2 semaines en centre / 6 semaines en entreprise par période',
                '3 jours en centre / 2 jours en entreprise par semaine',
                'Rythme adaptatif selon planning entreprise et modules pédagogiques',
                '1 semaine en centre / 2 semaines en entreprise en alternance',
            ];
            $session->setAlternanceRhythm($faker->randomElement($rhythms));

            $session->setFormation($formation);

            $manager->persist($session);
            $sessionCount++;
            $alternanceSessionCount++;

            // Create some registrations for alternance sessions (usually fewer)
            if ($currentRegistrations > 0) {
                for ($j = 0; $j < $currentRegistrations; $j++) {
                    $registration = new SessionRegistration();

                    $registration->setFirstName($faker->firstName);
                    $registration->setLastName($faker->lastName);
                    $registration->setEmail($faker->unique()->email);
                    $registration->setPhone($faker->phoneNumber);

                    // Alternance candidates almost always have company info or are seeking
                    if ($faker->boolean(90)) {
                        if ($faker->boolean(70)) { // 70% already have company
                            $registration->setCompany($faker->company);
                            $registration->setPosition($faker->jobTitle);
                        } else { // 30% are looking for company
                            $registration->setCompany('En recherche d\'entreprise d\'accueil');
                            $registration->setPosition('Candidat alternance');
                        }
                    }

                    // Alternance-specific notes
                    if ($faker->boolean(60)) {
                        $alternanceRegNotes = [
                            'Candidat motivé avec projet professionnel défini',
                            'Recherche active d\'entreprise d\'accueil en cours',
                            'Expérience préalable dans le secteur d\'activité',
                            'Besoin d\'accompagnement pour recherche entreprise',
                            'Entreprise d\'accueil déjà identifiée',
                            'Reconversion professionnelle via alternance',
                            'Validation des prérequis à confirmer par entretien',
                        ];
                        $registration->setNotes($faker->randomElement($alternanceRegNotes));
                    }

                    // Registration date (earlier for alternance due to preparation time)
                    $registrationDate = $faker->dateTimeBetween($startDate->format('Y-m-d') . ' -4 months', $startDate->format('Y-m-d') . ' -1 month');
                    $registration->setCreatedAt(DateTimeImmutable::createFromMutable($registrationDate));
                    $registration->setUpdatedAt(DateTimeImmutable::createFromMutable($registrationDate));

                    // Status for alternance registrations
                    $alternanceRegStatuses = ['pending', 'confirmed', 'cancelled'];
                    $alternanceRegWeights = [30, 60, 10]; // More pending due to company search
                    $regStatus = $faker->randomElement(array_merge(
                        array_fill(0, $alternanceRegWeights[0], $alternanceRegStatuses[0]),
                        array_fill(0, $alternanceRegWeights[1], $alternanceRegStatuses[1]),
                        array_fill(0, $alternanceRegWeights[2], $alternanceRegStatuses[2]),
                    ));
                    $registration->setStatus($regStatus);

                    $registration->setSession($session);

                    $manager->persist($registration);
                    $registrationCount++;
                }
            }
        }
    }
}
