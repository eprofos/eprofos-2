<?php

namespace App\DataFixtures;

use App\Entity\Alternance\CompanyVisit;
use App\Entity\User\Student;
use App\Entity\User\Teacher;
use App\Entity\User\Mentor;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Faker\Factory;

/**
 * Fixtures for CompanyVisit entities
 * 
 * Creates realistic company visits by pedagogical supervisors for apprenticeship
 * monitoring and evaluation, supporting Qualiopi compliance requirements.
 */
class CompanyVisitFixtures extends Fixture implements DependentFixtureInterface
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
        
        $visitTypes = [
            CompanyVisit::TYPE_INTEGRATION,
            CompanyVisit::TYPE_FOLLOW_UP,
            CompanyVisit::TYPE_EVALUATION,
            CompanyVisit::TYPE_PROBLEM_SOLVING,
            CompanyVisit::TYPE_FINAL_ASSESSMENT
        ];
        
        // CompanyVisit doesn't have status - we'll use completion logic instead
        $completed = [true, false]; // true = completed, false = planned
        
        // Sample visit objectives
        $objectiveTemplates = [
            'integration' => [
                'Vérifier l\'intégration de l\'apprenti dans l\'équipe',
                'Évaluer l\'adéquation du poste avec la formation',
                'S\'assurer de la qualité de l\'accueil en entreprise',
                'Identifier les besoins d\'accompagnement'
            ],
            'follow_up' => [
                'Faire le point sur l\'acquisition des compétences',
                'Évaluer la progression de l\'apprenti',
                'Identifier les difficultés rencontrées',
                'Ajuster le parcours si nécessaire'
            ],
            'evaluation' => [
                'Évaluer les compétences acquises en entreprise',
                'Valider les objectifs de la période',
                'Préparer l\'évaluation certificative',
                'Faire le bilan avec le tuteur'
            ],
            'problem_solving' => [
                'Analyser la situation problématique',
                'Rechercher des solutions avec l\'équipe',
                'Mettre en place un plan d\'action',
                'Planifier le suivi rapproché'
            ]
        ];
        
        // Sample recommendations
        $recommendationTemplates = [
            'Poursuivre l\'accompagnement selon le rythme actuel',
            'Renforcer l\'encadrement sur les compétences techniques',
            'Développer l\'autonomie de l\'apprenti progressivement',
            'Améliorer la communication entre tuteur et apprenti',
            'Organiser des points réguliers avec l\'équipe',
            'Adapter les missions aux capacités actuelles',
            'Prévoir une formation complémentaire pour le tuteur',
            'Mettre en place un suivi hebdomadaire temporaire'
        ];
        
        // Create 40 company visits
        for ($i = 0; $i < 40; $i++) {
            $visit = new CompanyVisit();
            
            // Random entities
            $student = $faker->randomElement($students);
            $teacher = $faker->randomElement($teachers);
            $mentor = $faker->randomElement($mentors);
            $type = $faker->randomElement($visitTypes);
            $isCompleted = $faker->randomElement($completed);
            
            // Basic properties
            $visit->setStudent($student);
            $visit->setVisitor($teacher); // Correct method name
            $visit->setMentor($mentor); // Correct method name
            $visit->setVisitType($type);
            
            // Date logic based on completion status
            if ($isCompleted) {
                $visitDate = $faker->dateTimeBetween('-3 months', 'now');
            } else {
                $visitDate = $faker->dateTimeBetween('+1 week', '+2 months');
            }
            $visit->setVisitDate(\DateTimeImmutable::createFromMutable($visitDate));
            
            // Duration
            $visit->setDuration($faker->numberBetween(90, 240)); // 1.5-4 hours
            
            // Objectives based on visit type (use objectivesChecked)
            $typeKey = match($type) {
                CompanyVisit::TYPE_INTEGRATION => 'integration',
                CompanyVisit::TYPE_FOLLOW_UP => 'follow_up',
                CompanyVisit::TYPE_EVALUATION => 'evaluation',
                CompanyVisit::TYPE_PROBLEM_SOLVING => 'problem_solving',
                CompanyVisit::TYPE_FINAL_ASSESSMENT => 'evaluation'
            };
            
            $objectives = $faker->randomElements($objectiveTemplates[$typeKey], $faker->numberBetween(2, 4));
            $visit->setObjectivesChecked($objectives);
            
            // If completed, add evaluation data
            if ($isCompleted) {
                // Evaluation scores (1-10) - use correct method names
                $visit->setOverallRating($faker->numberBetween(6, 10));
                $visit->setIntegrationRating($faker->numberBetween(5, 10));
                $visit->setSupervisionRating($faker->numberBetween(6, 10));
                $visit->setWorkingConditionsRating($faker->numberBetween(7, 10));
                
                // Visit report
                $visit->setVisitReport($faker->paragraphs($faker->numberBetween(2, 4), true));
                
                // Observed activities
                $activities = [
                    'Observation du poste de travail',
                    'Entretien avec le tuteur',
                    'Discussion avec l\'apprenti',
                    'Visite des locaux',
                    'Analyse des tâches confiées'
                ];
                $visit->setObservedActivities($faker->randomElements($activities, $faker->numberBetween(2, 4)));
                
                // Strengths and improvement areas
                $visit->setStrengths($faker->sentences($faker->numberBetween(2, 4)));
                $visit->setImprovementAreas($faker->sentences($faker->numberBetween(1, 3)));
                
                // Feedback
                $visit->setMentorFeedback($faker->paragraph());
                $visit->setStudentFeedback($faker->paragraph());
                
                // Recommendations
                $recommendations = $faker->randomElements($recommendationTemplates, $faker->numberBetween(1, 3));
                $visit->setRecommendations($recommendations);
                
                // Follow-up needed
                $visit->setFollowUpRequired($faker->boolean(30));
                if ($visit->isFollowUpRequired()) {
                    $followUpDate = clone $visitDate;
                    $followUpDate->modify('+' . $faker->numberBetween(2, 8) . ' weeks');
                    $visit->setNextVisitDate(\DateTimeImmutable::createFromMutable($followUpDate));
                }
                
                // Notes for additional information
                $visit->setNotes('Visite réalisée le ' . $visitDate->format('d/m/Y') . '. ' . $faker->sentence());
            } else {
                // Planned visit
                $visit->setNotes('Visite planifiée pour le ' . $visitDate->format('d/m/Y'));
            }
            
            $manager->persist($visit);
            
            // Add reference for other fixtures
            $this->addReference('company-visit-' . $i, $visit);
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
