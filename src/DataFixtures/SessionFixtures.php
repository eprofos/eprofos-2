<?php

namespace App\DataFixtures;

use App\Entity\Formation;
use App\Entity\Session;
use App\Entity\SessionRegistration;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class SessionFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
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

        foreach ($formations as $formation) {
            // Create 2-4 sessions per formation
            $sessionsToCreate = $faker->numberBetween(2, 4);
            
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
                    'Session intra-entreprise'
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
                    'Nantes - Espace formation Atlantique'
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
                        array_fill(0, $weights[2], $statuses[2])
                    ));
                } else {
                    $statuses = ['completed', 'cancelled'];
                    $weights = [90, 10]; // 90% completed, 10% cancelled
                    $status = $faker->randomElement(array_merge(
                        array_fill(0, $weights[0], $statuses[0]),
                        array_fill(0, $weights[1], $statuses[1])
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
                    $session->setPrice($faker->randomFloat(2, 300, 2000));
                }
                
                // Description
                $descriptions = [
                    'Session pratique avec études de cas réels et mise en situation.',
                    'Formation intensive avec accompagnement personnalisé.',
                    'Session inter-entreprises favorisant les échanges d\'expériences.',
                    'Formation en petit groupe pour un accompagnement optimal.',
                    'Session avec certification à l\'issue de la formation.',
                    'Formation avec support pédagogique complet et exercices pratiques.'
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
                        'Attestation de formation remise en fin de session'
                    ];
                    $session->setNotes($faker->randomElement($notes));
                }
                
                // Link to formation
                $session->setFormation($formation);
                
                $manager->persist($session);
                $sessionCount++;
                
                // Create some registrations for this session
                if ($currentRegistrations > 0 && in_array($status, ['open', 'full', 'completed'])) {
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
                                'Formation dans le cadre d\'une reconversion'
                            ];
                            $registration->setNotes($faker->randomElement($notes));
                        }
                        
                        // Registration date
                        $registrationDate = $faker->dateTimeBetween($startDate->format('Y-m-d') . ' -2 months', $startDate->format('Y-m-d') . ' -1 day');
                        $registration->setCreatedAt(\DateTimeImmutable::createFromMutable($registrationDate));
                        $registration->setUpdatedAt(\DateTimeImmutable::createFromMutable($registrationDate));
                        
                        // Status
                        if ($status === 'completed') {
                            $regStatuses = ['confirmed', 'attended', 'no_show'];
                            $regWeights = [10, 80, 10]; // Most attended
                            $regStatus = $faker->randomElement(array_merge(
                                array_fill(0, $regWeights[0], $regStatuses[0]),
                                array_fill(0, $regWeights[1], $regStatuses[1]),
                                array_fill(0, $regWeights[2], $regStatuses[2])
                            ));
                        } else {
                            $regStatuses = ['pending', 'confirmed', 'cancelled'];
                            $regWeights = [20, 70, 10]; // Most confirmed
                            $regStatus = $faker->randomElement(array_merge(
                                array_fill(0, $regWeights[0], $regStatuses[0]),
                                array_fill(0, $regWeights[1], $regStatuses[1]),
                                array_fill(0, $regWeights[2], $regStatuses[2])
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
        }
        
        $manager->flush();
        
        echo "✅ Sessions: Created {$sessionCount} sessions with {$registrationCount} registrations\n";
    }

    public function getDependencies(): array
    {
        return [
            FormationFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['session'];
    }
}
