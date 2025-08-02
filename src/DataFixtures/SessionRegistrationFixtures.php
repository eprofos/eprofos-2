<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\CRM\Prospect;
use App\Entity\Training\Session;
use App\Entity\Training\SessionRegistration;
use App\Entity\User\Student;
use DateTime;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;

/**
 * SessionRegistration fixtures for EPROFOS platform.
 *
 * Creates realistic session registrations to demonstrate registration management,
 * participant tracking, and document delivery for Qualiopi compliance.
 */
class SessionRegistrationFixtures extends Fixture implements DependentFixtureInterface
{
    private Generator $faker;

    // Common French companies for realistic data
    private array $companies = [
        'TechCorp SARL',
        'Digital Solutions',
        'Innovate Consulting',
        'Business Excellence',
        'Formation Plus',
        'ProSkills Formation',
        'Expert Training',
        'Modern Learning',
        'Skills Development',
        'Professional Growth',
        'Advanced Training',
        'Corporate Learning',
        'Excellence Formation',
        'Future Skills',
        'Training Solutions',
        'Alpha Consulting',
        'Beta Technologies',
        'Gamma Services',
        'Delta Formation',
        'Epsilon Group',
    ];

    // Common job positions
    private array $positions = [
        'Développeur',
        'Chef de projet',
        'Responsable commercial',
        'Assistant administratif',
        'Technicien informatique',
        'Manager',
        'Consultant',
        'Analyste',
        'Coordinateur',
        'Superviseur',
        'Directeur',
        'Responsable RH',
        'Comptable',
        'Secrétaire',
        'Formateur',
        'Responsable qualité',
        'Chargé de communication',
        'Designer',
        'Architecte logiciel',
        'Data Analyst',
    ];

    public function __construct()
    {
        $this->faker = Factory::create('fr_FR');
    }

    /**
     * Load session registration fixtures.
     */
    public function load(ObjectManager $manager): void
    {
        $sessions = $manager->getRepository(Session::class)->findAll();
        $prospects = $manager->getRepository(Prospect::class)->findAll();
        $students = $manager->getRepository(Student::class)->findAll();

        if (empty($sessions)) {
            return;
        }

        $registrationCount = 0;

        foreach ($sessions as $session) {
            // Create 2-8 registrations per session, but don't exceed max capacity
            $maxRegistrations = min(8, $session->getMaxCapacity() ?: 10);
            $registrationsToCreate = $this->faker->numberBetween(2, $maxRegistrations);

            for ($i = 0; $i < $registrationsToCreate; $i++) {
                $registration = $this->createSessionRegistration($session, $prospects, $students);
                $manager->persist($registration);
                $registrationCount++;
            }
        }

        $manager->flush();
    }

    /**
     * Define fixture dependencies.
     */
    public function getDependencies(): array
    {
        return [
            SessionFixtures::class,
            ProspectFixtures::class,
            StudentFixtures::class,
        ];
    }

    /**
     * Create a single session registration.
     */
    private function createSessionRegistration(Session $session, array $prospects, array $students): SessionRegistration
    {
        $registration = new SessionRegistration();
        $registration->setSession($session);

        // Set createdAt using proper DateTime manipulation
        $sessionStart = $session->getStartDate();

        // Convert to mutable DateTime for calculations
        if ($sessionStart instanceof DateTimeImmutable) {
            $sessionStartMutable = new DateTime($sessionStart->format('Y-m-d H:i:s'));
        } else {
            $sessionStartMutable = clone $sessionStart;
        }

        // Simple approach: create registration 1-30 days before session
        $startDate = new DateTime($sessionStartMutable->format('Y-m-d H:i:s'));
        $startDate->modify('-30 days');
        $endDate = new DateTime($sessionStartMutable->format('Y-m-d H:i:s'));
        $endDate->modify('-1 hour');

        // Ensure start date is before end date
        if ($startDate >= $endDate) {
            $startDate = new DateTime($sessionStartMutable->format('Y-m-d H:i:s'));
            $startDate->modify('-31 days');
        }

        $createdAt = $this->faker->dateTimeBetween($startDate, $endDate);
        $registration->setCreatedAt(DateTimeImmutable::createFromMutable($createdAt));
        $registration->setUpdatedAt(DateTimeImmutable::createFromMutable($createdAt));

        // 60% chance to link with existing prospect, 40% chance for new participant
        if (!empty($prospects) && $this->faker->boolean(60)) {
            $prospect = $this->faker->randomElement($prospects);
            $registration->setProspect($prospect);

            // Use prospect's information
            $registration->setFirstName($prospect->getFirstName());
            $registration->setLastName($prospect->getLastName());
            $registration->setEmail($prospect->getEmail());
            $registration->setPhone($prospect->getPhone());
            $registration->setCompany($prospect->getCompany());
            $registration->setPosition($prospect->getPosition());
        } else {
            // Generate new participant information
            $registration->setFirstName($this->faker->firstName());
            $registration->setLastName($this->faker->lastName());
            $registration->setEmail($this->faker->email());
            $registration->setPhone($this->faker->optional(0.8)->phoneNumber());
            $registration->setCompany($this->faker->optional(0.7)->randomElement($this->companies));
            $registration->setPosition($this->faker->optional(0.6)->randomElement($this->positions));
        }

        // 30% chance to link with existing student (for students taking additional sessions)
        if (!empty($students) && $this->faker->boolean(30)) {
            $student = $this->faker->randomElement($students);
            $registration->setLinkedStudent($student);
            $registration->setLinkedAt(new DateTimeImmutable());
        }

        // Set registration status
        $statusDistribution = [
            'confirmed' => 0.60,    // 60%
            'pending' => 0.15,      // 15%
            'attended' => 0.15,     // 15%
            'cancelled' => 0.07,    // 7%
            'no_show' => 0.03,      // 3%
        ];

        $status = $this->faker->randomElement(array_keys($statusDistribution));
        $registration->setStatus($status);

        // Set confirmation date for confirmed registrations
        if (in_array($status, ['confirmed', 'attended', 'no_show'], true)) {
            // Ensure we have valid date range
            $createdAt = $registration->getCreatedAt();
            $sessionStart = $session->getStartDate();

            // Convert to mutable DateTime for calculations
            $createdAtMutable = new DateTime($createdAt->format('Y-m-d H:i:s'));
            $sessionStartMutable = new DateTime($sessionStart->format('Y-m-d H:i:s'));
            $nowMutable = new DateTime();

            // Determine end date for confirmation
            $endDate = min($nowMutable, $sessionStartMutable);

            // Only set confirmedAt if we have a valid range
            if ($createdAtMutable < $endDate) {
                $confirmedAt = $this->faker->dateTimeBetween($createdAtMutable, $endDate);
                $registration->setConfirmedAt($confirmedAt);
            } else {
                // Fallback: set confirmation to creation date + 1 hour
                $confirmedAt = new DateTime($createdAtMutable->format('Y-m-d H:i:s'));
                $confirmedAt->modify('+1 hour');
                $registration->setConfirmedAt($confirmedAt);
            }
        }

        // Add special requirements (20% chance)
        if ($this->faker->boolean(20)) {
            $specialRequirements = [
                'Adaptation pour personne malentendante',
                'Accès PMR nécessaire',
                'Régime alimentaire sans gluten',
                'Besoin de pauses fréquentes pour raisons médicales',
                'Support de cours en gros caractères',
                'Interprète en langue des signes',
                'Adaptation du rythme de formation',
                'Matériel informatique adapté',
                'Formation en langue anglaise si possible',
                'Horaires aménagés pour transport en commun',
            ];
            $registration->setSpecialRequirements($this->faker->randomElement($specialRequirements));
        }

        // Add admin notes (15% chance)
        if ($this->faker->boolean(15)) {
            $notes = [
                'Participant très motivé, demande beaucoup de formations',
                'Client fidèle de l\'entreprise',
                'Première formation, nécessite un accompagnement particulier',
                'Demande de facture spécifique pour OPCO',
                'Contact préférentiel par email',
                'Participant avec expertise technique avancée',
                'Formation financée par Pôle Emploi',
                'Demande de certificat de présence',
                'Inscription tardive, vérifier la disponibilité',
                'Participant référent pour son équipe',
            ];
            $registration->setNotes($this->faker->randomElement($notes));
        }

        // Handle document delivery (70% chance for confirmed registrations)
        if ($status === 'confirmed' && $this->faker->boolean(70)) {
            $this->handleDocumentDelivery($registration);
        }

        // Add additional data (30% chance)
        if ($this->faker->boolean(30)) {
            $additionalData = [
                'registration_source' => $this->faker->randomElement([
                    'Site web', 'Recommandation', 'Téléphone', 'Email', 'Salon professionnel', 'Partenaire',
                ]),
                'dietary_requirements' => $this->faker->optional(0.3)->randomElement([
                    'Végétarien', 'Végan', 'Sans gluten', 'Halal', 'Casher', 'Allergies alimentaires',
                ]),
                'transport_mode' => $this->faker->randomElement([
                    'Voiture personnelle', 'Transport en commun', 'Train', 'Avion', 'Covoiturage', 'Vélo',
                ]),
                'experience_level' => $this->faker->randomElement([
                    'Débutant', 'Intermédiaire', 'Avancé', 'Expert',
                ]),
                'motivation' => $this->faker->optional(0.4)->randomElement([
                    'Évolution professionnelle',
                    'Reconversion',
                    'Mise à jour des compétences',
                    'Obligation légale',
                    'Curiosité personnelle',
                ]),
            ];
            $registration->setAdditionalData(array_filter($additionalData));
        }

        return $registration;
    }

    /**
     * Handle document delivery for a registration.
     */
    private function handleDocumentDelivery(SessionRegistration $registration): void
    {
        // Documents delivered (90% chance)
        if ($this->faker->boolean(90)) {
            $startDate = $registration->getConfirmedAt() ?: $registration->getCreatedAt();
            $now = new DateTime();

            // Convert to mutable DateTime for calculations
            if ($startDate instanceof DateTimeImmutable) {
                $startDateMutable = new DateTime($startDate->format('Y-m-d H:i:s'));
            } else {
                $startDateMutable = clone $startDate;
            }

            // Simple approach: always deliver documents between confirmation and now (or +7 days if future)
            if ($startDateMutable <= $now) {
                // Past or present confirmation - deliver between then and now
                $deliveredAt = $this->faker->dateTimeBetween($startDateMutable, $now);
            } else {
                // Future confirmation - deliver sometime after that
                $endDate = new DateTime($startDateMutable->format('Y-m-d H:i:s'));
                $endDate->modify('+7 days');
                $deliveredAt = $this->faker->dateTimeBetween($startDateMutable, $endDate);
            }

            $registration->setDocumentsDeliveredAt($deliveredAt);

            // Documents acknowledged (70% chance if delivered)
            if ($this->faker->boolean(70)) {
                // Set acknowledgment 1-3 days after delivery
                $deliveryMutable = new DateTime($deliveredAt->format('Y-m-d H:i:s'));
                $endAckDate = new DateTime($deliveredAt->format('Y-m-d H:i:s'));
                $endAckDate->modify('+3 days');
                $acknowledgedAt = $this->faker->dateTimeBetween($deliveryMutable, $endAckDate);
                $registration->setDocumentsAcknowledgedAt($acknowledgedAt);
            }

            // Generate acknowledgment token for security
            $registration->generateDocumentAcknowledgmentToken();
        }
    }
}
