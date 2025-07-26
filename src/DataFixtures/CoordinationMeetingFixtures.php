<?php

namespace App\DataFixtures;

use App\Entity\Alternance\CoordinationMeeting;
use App\Entity\User\Student;
use App\Entity\User\Teacher;
use App\Entity\User\Mentor;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Faker\Factory;

/**
 * Fixtures for CoordinationMeeting entities
 * 
 * Creates realistic coordination meetings between training centers and companies
 * for apprenticeship program management and Qualiopi compliance.
 */
class CoordinationMeetingFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        
        // Get existing entities to reference
        $students = $manager->getRepository(Student::class)->findAll();
        $teachers = $manager->getRepository(Teacher::class)->findAll();
        $mentors = $manager->getRepository(Mentor::class)->findAll();
        
        if (empty($students) || empty($teachers) || empty($mentors)) {
            // Skip if required entities don't exist yet
            return;
        }
        
        $meetingTypes = [
            CoordinationMeeting::TYPE_PREPARATORY,
            CoordinationMeeting::TYPE_FOLLOW_UP,
            CoordinationMeeting::TYPE_EVALUATION,
            CoordinationMeeting::TYPE_PROBLEM_SOLVING
        ];
        
        $statuses = [
            CoordinationMeeting::STATUS_PLANNED,
            CoordinationMeeting::STATUS_COMPLETED,
            CoordinationMeeting::STATUS_CANCELLED,
            CoordinationMeeting::STATUS_POSTPONED
        ];
        
        // Sample agenda items
        $agendaTemplates = [
            'preparatory' => [
                'Présentation du parcours de formation',
                'Définition des objectifs pédagogiques',
                'Planification des activités en entreprise',
                'Modalités de suivi et d\'évaluation'
            ],
            'follow_up' => [
                'Bilan des acquis depuis la dernière rencontre',
                'Difficultés rencontrées et solutions',
                'Adaptation du parcours si nécessaire',
                'Préparation de la prochaine période'
            ],
            'evaluation' => [
                'Évaluation des compétences acquises',
                'Validation des objectifs atteints',
                'Bilan de la période en entreprise',
                'Perspectives d\'évolution'
            ],
            'problem_solving' => [
                'Analyse du problème identifié',
                'Recherche de solutions adaptées',
                'Plan d\'action corrective',
                'Modalités de suivi rapproché'
            ]
        ];
        
        // Sample decisions
        $decisionTemplates = [
            'Poursuite du parcours selon la planification initiale',
            'Adaptation des objectifs pédagogiques en fonction des besoins',
            'Renforcement de l\'accompagnement sur certaines compétences',
            'Programmation d\'une visite complémentaire en entreprise',
            'Mise en place d\'un suivi hebdomadaire',
            'Ajustement des missions confiées à l\'apprenti',
            'Organisation d\'une formation complémentaire pour le tuteur'
        ];
        
        // Create 50 coordination meetings
        for ($i = 0; $i < 50; $i++) {
            $meeting = new CoordinationMeeting();
            
            // Random entities
            $student = $faker->randomElement($students);
            $teacher = $faker->randomElement($teachers);
            $mentor = $faker->randomElement($mentors);
            $type = $faker->randomElement($meetingTypes);
            $status = $faker->randomElement($statuses);
            
            // Basic properties
            $meeting->setStudent($student);
            $meeting->setPedagogicalSupervisor($teacher);
            $meeting->setMentor($mentor);
            $meeting->setType($type);
            $meeting->setStatus($status);
            
            // Date logic based on status
            $baseDate = $faker->dateTimeBetween('-6 months', '+3 months');
            $meeting->setDate(\DateTimeImmutable::createFromMutable($baseDate));
            
            if (in_array($status, [CoordinationMeeting::STATUS_COMPLETED, CoordinationMeeting::STATUS_POSTPONED])) {
                $confirmDate = clone $baseDate;
                $confirmDate->modify('-' . $faker->numberBetween(1, 14) . ' days');
                // Note: No setConfirmedAt method in entity
            }
            
            // Duration and location
            $meeting->setDuration($faker->numberBetween(60, 180)); // 1-3 hours
            $meeting->setLocation($faker->randomElement([
                CoordinationMeeting::LOCATION_TRAINING_CENTER,
                CoordinationMeeting::LOCATION_COMPANY,
                CoordinationMeeting::LOCATION_VIDEO_CONFERENCE,
                CoordinationMeeting::LOCATION_PHONE
            ]));
            
            // Agenda based on meeting type
            $typeKey = match($type) {
                CoordinationMeeting::TYPE_PREPARATORY => 'preparatory',
                CoordinationMeeting::TYPE_FOLLOW_UP => 'follow_up',
                CoordinationMeeting::TYPE_EVALUATION => 'evaluation',
                CoordinationMeeting::TYPE_PROBLEM_SOLVING => 'problem_solving'
            };
            
            $agenda = $faker->randomElements($agendaTemplates[$typeKey], $faker->numberBetween(2, 4));
            $meeting->setAgenda($agenda);
            
            // Notes and discussion points
            $meeting->setNotes($faker->paragraphs($faker->numberBetween(2, 4), true));
            $meeting->setDiscussionPoints($faker->sentences($faker->numberBetween(2, 3)));
            
            // If completed, add completion data
            if ($status === CoordinationMeeting::STATUS_COMPLETED) {
                // Note: No setCompletedAt method in entity, handled automatically
                
                // Decisions
                $decisions = $faker->randomElements($decisionTemplates, $faker->numberBetween(1, 3));
                $meeting->setDecisions($decisions);
                
                // Meeting report
                $meeting->setMeetingReport($faker->paragraphs($faker->numberBetween(2, 3), true));
                
                // Satisfaction rating
                $meeting->setSatisfactionRating($faker->numberBetween(3, 5));
                
                // Action plan
                $followUpActions = [];
                for ($j = 0; $j < $faker->numberBetween(1, 3); $j++) {
                    $followUpActions[] = [
                        'action' => $faker->sentence(),
                        'responsible' => $faker->randomElement(['Superviseur pédagogique', 'Tuteur entreprise', 'Apprenti']),
                        'deadline' => $faker->dateTimeBetween('+1 week', '+2 months')->format('Y-m-d'),
                        'status' => $faker->randomElement(['pending', 'in_progress', 'completed'])
                    ];
                }
                $meeting->setActionPlan($followUpActions);
                
                // Next meeting if applicable
                if ($faker->boolean(70)) {
                    $nextDate = clone $baseDate;
                    $nextDate->modify('+' . $faker->numberBetween(4, 12) . ' weeks');
                    $meeting->setNextMeetingDate(\DateTimeImmutable::createFromMutable($nextDate));
                }
            }
            
            // Cancelled meetings
            if ($status === CoordinationMeeting::STATUS_CANCELLED) {
                // Note: No setCancelledAt or setCancellationReason methods in entity
                // Status is managed through the status field
                $meeting->setNotes('Réunion annulée - Raison: ' . $faker->randomElement([
                    'Indisponibilité du tuteur entreprise',
                    'Maladie de l\'apprenti',
                    'Urgence en entreprise',
                    'Problème technique (visio)',
                    'Report à la demande de l\'apprenti'
                ]));
            }
            
            $manager->persist($meeting);
            
            // Add reference for other fixtures
            $this->addReference('coordination-meeting-' . $i, $meeting);
        }
        
        $manager->flush();
    }
    
    public function getDependencies(): array
    {
        return [
            StudentFixtures::class,
            TeacherFixtures::class,
            MentorFixtures::class,
        ];
    }
}
