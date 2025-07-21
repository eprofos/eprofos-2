<?php

namespace App\DataFixtures;

use App\Entity\Prospect;
use App\Entity\ProspectNote;
use App\Entity\User\Admin;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

/**
 * Prospect and ProspectNote Fixtures
 * 
 * Loads realistic test data for the prospect management system.
 * Creates prospects with various statuses, priorities, and associated notes.
 * These are independent prospects not linked to contact requests or session registrations.
 */
class ProspectFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        
        // Get existing users
        $users = $manager->getRepository(Admin::class)->findAll();
        
        if (empty($users)) {
            echo "❌ No users found. Please create users first.\n";
            return;
        }

        $prospects = [];
        $companies = [
            'TechCorp SARL', 'Innovation Solutions', 'Digital Factory', 'Conseil & Formation Plus',
            'Excellence RH', 'Formation Pro', 'Développement Durable SA', 'Expertise Métier',
            'Solutions Numériques', 'Stratégie & Performance', 'Avenir Formation', 'Compétences Plus'
        ];
        
        $positions = [
            'Directeur des Ressources Humaines', 'Responsable Formation', 'Manager',
            'Directeur Général', 'Responsable Développement', 'Chef de Projet',
            'Coordinateur Formation', 'Responsable RH', 'Directeur Opérationnel',
            'Consultant', 'Responsable Qualité', 'Chef d\'équipe'
        ];

        $sources = ['website', 'referral', 'linkedin', 'email_campaign', 'phone_call', 'trade_show', 'partner'];
        $statuses = ['new', 'contacted', 'qualified', 'proposal_sent', 'negotiation', 'converted', 'lost'];
        $priorities = ['low', 'medium', 'high', 'urgent'];

        $tags = [
            ['formation', 'urgent'], ['digital', 'transformation'], ['management', 'leadership'],
            ['technical', 'skills'], ['compliance', 'qualiopi'], ['team', 'building'],
            ['customer', 'service'], ['sales', 'performance'], ['project', 'management'],
            ['innovation', 'creativity'], ['communication', 'skills'], ['strategic', 'planning']
        ];

        // Create 30 prospects
        for ($i = 0; $i < 30; $i++) {
            $prospect = new Prospect();
            
            $firstName = $faker->firstName();
            $lastName = $faker->lastName();
            
            $prospect->setFirstName($firstName)
                ->setLastName($lastName)
                ->setEmail(strtolower($firstName . '.' . $lastName . '@' . $faker->freeEmailDomain()))
                ->setPhone($faker->phoneNumber())
                ->setCompany($faker->randomElement($companies))
                ->setPosition($faker->randomElement($positions))
                ->setStatus($faker->randomElement($statuses))
                ->setPriority($faker->randomElement($priorities))
                ->setSource($faker->randomElement($sources))
                ->setDescription($faker->optional(0.7)->paragraph())
                ->setEstimatedBudget($faker->optional(0.6)->numberBetween(2000, 50000))
                ->setAssignedTo($faker->randomElement($users))
                ->setCreatedAt($faker->dateTimeBetween('-6 months', 'now'))
                ->setTags($faker->randomElement($tags));

            // Set follow-up dates based on status
            if (in_array($prospect->getStatus(), ['contacted', 'qualified', 'proposal_sent', 'negotiation'])) {
                $prospect->setLastContactDate($faker->dateTimeBetween('-30 days', 'now'));
                $prospect->setNextFollowUpDate($faker->dateTimeBetween('now', '+30 days'));
            }

            if (in_array($prospect->getStatus(), ['proposal_sent', 'negotiation', 'converted'])) {
                $prospect->setExpectedClosureDate($faker->dateTimeBetween('now', '+3 months'));
            }

            $manager->persist($prospect);
            $prospects[] = $prospect;
        }

        $manager->flush();

        // Create prospect notes
        $noteTypes = ['note', 'call', 'email', 'meeting', 'task', 'reminder'];
        $noteStatuses = ['pending', 'in_progress', 'completed'];
        
        $callOutcomes = [
            'Prospect très intéressé, demande un devis détaillé',
            'Réunion programmée pour la semaine prochaine',
            'Budget insuffisant pour cette année, reporter à 2026',
            'Décision en attente du comité de direction',
            'Très satisfait de notre approche, validation en cours'
        ];

        $meetingNotes = [
            'Présentation de notre offre formation leadership',
            'Analyse des besoins en formation digitale',
            'Discussion sur les modalités pédagogiques',
            'Présentation des certifications disponibles',
            'Évaluation du niveau actuel des équipes'
        ];

        $emailSubjects = [
            'Suite à notre conversation téléphonique',
            'Proposition commerciale comme convenu',
            'Documentation complémentaire',
            'Planification de la formation',
            'Confirmation des modalités'
        ];

        $taskTitles = [
            'Préparer le devis personnalisé',
            'Envoyer la documentation technique',
            'Relancer dans 2 semaines',
            'Programmer une démonstration',
            'Valider les dates de formation'
        ];

        foreach ($prospects as $prospect) {
            $notesCount = $faker->numberBetween(2, 6);
            
            for ($j = 0; $j < $notesCount; $j++) {
                $note = new ProspectNote();
                $type = $faker->randomElement($noteTypes);
                
                $note->setProspect($prospect)
                    ->setCreatedBy($prospect->getAssignedTo() ?: $faker->randomElement($users))
                    ->setType($type)
                    ->setStatus($faker->randomElement($noteStatuses))
                    ->setIsImportant($faker->boolean(20))
                    ->setIsPrivate($faker->boolean(10))
                    ->setCreatedAt($faker->dateTimeBetween($prospect->getCreatedAt(), 'now'));

                // Generate content based on note type
                switch ($type) {
                    case 'call':
                        $note->setTitle('Appel téléphonique - ' . $faker->dateTime()->format('d/m/Y H:i'))
                            ->setContent($faker->randomElement($callOutcomes) . "\n\nDurée: " . $faker->numberBetween(10, 45) . " minutes");
                        break;
                        
                    case 'email':
                        $note->setTitle($faker->randomElement($emailSubjects))
                            ->setContent("Email envoyé avec les éléments suivants:\n" . $faker->paragraph());
                        break;
                        
                    case 'meeting':
                        $note->setTitle('Réunion - ' . $faker->randomElement($meetingNotes))
                            ->setContent("Participants: " . $prospect->getFullName() . " et " . $faker->name() . "\n\n" . $faker->paragraph());
                        if ($faker->boolean(30)) {
                            $note->setScheduledAt($faker->dateTimeBetween('now', '+2 weeks'));
                        }
                        break;
                        
                    case 'task':
                        $note->setTitle($faker->randomElement($taskTitles))
                            ->setContent($faker->sentence());
                        if ($note->getStatus() === 'pending') {
                            $note->setScheduledAt($faker->dateTimeBetween('now', '+1 week'));
                        }
                        break;
                        
                    case 'reminder':
                        $note->setTitle('Rappel - Relance prospect')
                            ->setContent('Ne pas oublier de relancer ce prospect concernant ' . $faker->sentence())
                            ->setScheduledAt($faker->dateTimeBetween('now', '+2 weeks'));
                        break;
                        
                    default:
                        $note->setTitle('Note générale')
                            ->setContent($faker->paragraph());
                        break;
                }

                // Set completion date for completed notes
                if ($note->getStatus() === 'completed') {
                    $note->setCompletedAt($faker->dateTimeBetween($note->getCreatedAt(), 'now'));
                }

                // Add metadata for some notes
                if ($faker->boolean(30)) {
                    $metadata = [];
                    if ($type === 'call') {
                        $metadata = [
                            'duration_minutes' => $faker->numberBetween(5, 60),
                            'call_quality' => $faker->randomElement(['excellent', 'good', 'average']),
                            'next_action' => $faker->randomElement(['send_proposal', 'schedule_meeting', 'call_back'])
                        ];
                    } elseif ($type === 'email') {
                        $metadata = [
                            'email_opened' => $faker->boolean(70),
                            'links_clicked' => $faker->numberBetween(0, 3),
                            'attachments' => $faker->randomElements(['brochure.pdf', 'pricing.xlsx', 'calendar.ics'], $faker->numberBetween(0, 2))
                        ];
                    }
                    
                    if (!empty($metadata)) {
                        $note->setMetadata($metadata);
                    }
                }

                $manager->persist($note);
            }
        }

        $manager->flush();

        echo "✅ Prospects: Created " . count($prospects) . " prospects with notes\n";
    }

    /**
     * Define fixture dependencies
     */
    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }
}
