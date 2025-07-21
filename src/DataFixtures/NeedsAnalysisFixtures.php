<?php

namespace App\DataFixtures;

use App\Entity\CompanyNeedsAnalysis;
use App\Entity\Training\Formation;
use App\Entity\IndividualNeedsAnalysis;
use App\Entity\NeedsAnalysisRequest;
use App\Entity\User\Admin;
use App\Service\ProspectManagementService;
use App\Service\TokenGeneratorService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Psr\Log\LoggerInterface;

/**
 * Needs Analysis fixtures for EPROFOS platform
 * 
 * Creates realistic needs analysis requests, company analyses, and individual analyses
 * with various statuses and realistic data for testing and demonstration purposes.
 * Complies with Qualiopi 2.4 requirements for needs analysis documentation.
 * 
 * Each needs analysis request will automatically create a prospect through the
 * ProspectManagementService to test the unified prospect system.
 */
class NeedsAnalysisFixtures extends Fixture implements DependentFixtureInterface
{
    private Generator $faker;

    public function __construct(
        private TokenGeneratorService $tokenGenerator,
        private ProspectManagementService $prospectService,
        private LoggerInterface $logger
    ) {
        $this->faker = Factory::create('fr_FR');
    }

    // Company names for realistic data
    private array $companyNames = [
        'TechCorp SARL',
        'Formation Plus',
        'Digital Solutions',
        'Innovate Consulting',
        'Business Excellence',
        'ProSkills Formation',
        'Expert Training',
        'Modern Learning',
        'Skills Development',
        'Professional Growth',
        'Advanced Training',
        'Corporate Learning',
        'Excellence Formation',
        'Future Skills',
        'Training Solutions'
    ];

    // Activity sectors for companies
    private array $activitySectors = [
        'Informatique et services numériques',
        'Conseil en management',
        'Formation professionnelle',
        'Services aux entreprises',
        'Commerce et distribution',
        'Industrie manufacturière',
        'Santé et action sociale',
        'Transport et logistique',
        'Construction et BTP',
        'Finance et assurance'
    ];

    // OPCO names
    private array $opcoNames = [
        'OPCO Atlas',
        'OPCO EP',
        'OPCO Mobilités',
        'OPCO Santé',
        'OPCO Commerce',
        'AFDAS',
        'Constructys',
        'OCAPIAT',
        'OPCO 2i',
        'Uniformation'
    ];

    // Training titles for realistic data
    private array $trainingTitles = [
        'Développement Web avec PHP et Symfony',
        'Cybersécurité et Protection des Données',
        'Maîtrise d\'Excel Avancé et Power BI',
        'Leadership et Management d\'Équipe',
        'Gestion de Projet Agile - Scrum Master',
        'Anglais Professionnel - Business English',
        'Comptabilité Générale et Analyse Financière',
        'Marketing Digital et Réseaux Sociaux',
        'Recrutement et Gestion des Talents',
        'Lean Management et Amélioration Continue',
        'Communication Interpersonnelle',
        'Gestion du Stress et des Conflits',
        'Techniques de Vente et Négociation',
        'Bureautique Avancée',
        'Qualité et Certification ISO'
    ];

    /**
     * Load needs analysis fixtures
     */
    public function load(ObjectManager $manager): void
    {
    // Get admin for created_by_admin field
    $admin = $this->getReference(UserFixtures::ADMIN_USER_REFERENCE, Admin::class);
        
        // Get some formations for reference
        $formations = $manager->getRepository(Formation::class)->findAll();

        // Create 25 needs analysis requests with various statuses
        for ($i = 0; $i < 25; $i++) {
            $request = $this->createNeedsAnalysisRequest($admin, $formations);
            $manager->persist($request);

            // Create corresponding analysis if request is completed
            if ($request->getStatus() === NeedsAnalysisRequest::STATUS_COMPLETED) {
                if ($request->getType() === NeedsAnalysisRequest::TYPE_COMPANY) {
                    $companyAnalysis = $this->createCompanyNeedsAnalysis($request);
                    $manager->persist($companyAnalysis);
                } else {
                    $individualAnalysis = $this->createIndividualNeedsAnalysis($request);
                    $manager->persist($individualAnalysis);
                }
            }
        }

        $manager->flush();
        
        // Create prospects from needs analysis requests using the ProspectManagementService
        echo "Creating prospects from needs analysis requests...\n";
        $createdProspects = 0;
        
        $needsAnalysisRequests = $manager->getRepository(NeedsAnalysisRequest::class)->findAll();
        
        foreach ($needsAnalysisRequests as $request) {
            try {
                $prospect = $this->prospectService->createProspectFromNeedsAnalysis($request);
                $createdProspects++;
                
                $this->logger->info('Prospect created from needs analysis request fixture', [
                    'prospect_id' => $prospect->getId(),
                    'needs_analysis_request_id' => $request->getId(),
                    'email' => $request->getRecipientEmail()
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to create prospect from needs analysis request fixture', [
                    'needs_analysis_request_id' => $request->getId(),
                    'email' => $request->getRecipientEmail(),
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        echo "✅ Needs Analysis: Created " . count($needsAnalysisRequests) . " needs analysis requests\n";
        echo "✅ Prospects: Created {$createdProspects} prospects from needs analysis requests\n";
    }

    /**
     * Create a needs analysis request with realistic data
     */
    private function createNeedsAnalysisRequest(Admin $admin, array $formations): NeedsAnalysisRequest
    {
        $request = new NeedsAnalysisRequest();
        
        // Random type (60% company, 40% individual)
        $type = $this->faker->randomFloat() < 0.6 ? 
            NeedsAnalysisRequest::TYPE_COMPANY : 
            NeedsAnalysisRequest::TYPE_INDIVIDUAL;
        
        $request->setType($type);
        $request->setToken($this->tokenGenerator->generateToken());
    $request->setCreatedByAdmin($admin);

        // Set recipient information based on type
        if ($type === NeedsAnalysisRequest::TYPE_COMPANY) {
            $companyName = $this->faker->randomElement($this->companyNames);
            $request->setCompanyName($companyName);
            $request->setRecipientName($this->faker->firstName() . ' ' . $this->faker->lastName());
            $request->setRecipientEmail($this->faker->companyEmail());
        } else {
            $request->setRecipientName($this->faker->firstName() . ' ' . $this->faker->lastName());
            $request->setRecipientEmail($this->faker->email());
        }

        // Random formation reference (80% chance)
        if ($this->faker->randomFloat() < 0.8 && !empty($formations)) {
            $request->setFormation($this->faker->randomElement($formations));
        }

        // Set status with realistic distribution
        $statusDistribution = [
            NeedsAnalysisRequest::STATUS_PENDING => 0.15,    // 15%
            NeedsAnalysisRequest::STATUS_SENT => 0.25,       // 25%
            NeedsAnalysisRequest::STATUS_COMPLETED => 0.45,  // 45%
            NeedsAnalysisRequest::STATUS_EXPIRED => 0.10,    // 10%
            NeedsAnalysisRequest::STATUS_CANCELLED => 0.05   // 5%
        ];

        $status = $this->faker->randomElement(array_keys($statusDistribution));
        $request->setStatus($status);

        // Set dates based on status
        $createdAt = $this->faker->dateTimeBetween('-6 months', '-1 day');
        $request->setCreatedAt(\DateTimeImmutable::createFromMutable($createdAt));

        // Set expiration date (30 days from creation)
        $expiresAt = (clone $createdAt)->modify('+30 days');
        $request->setExpiresAt(\DateTimeImmutable::createFromMutable($expiresAt));

        // Set sent date if status is sent, completed, or expired
        if (in_array($status, [
            NeedsAnalysisRequest::STATUS_SENT,
            NeedsAnalysisRequest::STATUS_COMPLETED,
            NeedsAnalysisRequest::STATUS_EXPIRED
        ])) {
            $sentAt = $this->faker->dateTimeBetween($createdAt, $createdAt->format('Y-m-d') . ' +5 days');
            $request->setSentAt(\DateTimeImmutable::createFromMutable($sentAt));
        }

        // Set completed date if status is completed
        if ($status === NeedsAnalysisRequest::STATUS_COMPLETED) {
            $sentAtForCompleted = $request->getSentAt();
            $startDate = $sentAtForCompleted ? $sentAtForCompleted->format('Y-m-d H:i:s') : $createdAt;
            $completedAt = $this->faker->dateTimeBetween(
                $startDate,
                $createdAt->format('Y-m-d') . ' +20 days'
            );
            $request->setCompletedAt(\DateTimeImmutable::createFromMutable($completedAt));
        }

        // Set reminder date for sent requests (50% chance)
        if ($status === NeedsAnalysisRequest::STATUS_SENT && $this->faker->boolean(50)) {
            $sentAtForReminder = $request->getSentAt();
            $startDate = $sentAtForReminder ? $sentAtForReminder->format('Y-m-d H:i:s') : $createdAt;
            $reminderAt = $this->faker->dateTimeBetween(
                $startDate,
                'now'
            );
            $request->setLastReminderSentAt(\DateTimeImmutable::createFromMutable($reminderAt));
        }

        // Add admin notes (30% chance)
        if ($this->faker->boolean(30)) {
            $notes = [
                'Demande urgente - formation prévue pour le trimestre prochain',
                'Client existant - déjà formé sur d\'autres modules',
                'Demande spécifique pour adaptation handicap',
                'Formation en intra-entreprise demandée',
                'Besoin de certification à l\'issue de la formation',
                'Demande de devis détaillé en cours',
                'Contact téléphonique effectué - très motivé',
                'Demande liée à une évolution de poste'
            ];
            $request->setAdminNotes($this->faker->randomElement($notes));
        }

        return $request;
    }

    /**
     * Create a company needs analysis with realistic data
     */
    private function createCompanyNeedsAnalysis(NeedsAnalysisRequest $request): CompanyNeedsAnalysis
    {
        $analysis = new CompanyNeedsAnalysis();
        $analysis->setNeedsAnalysisRequest($request);

        // Company information
        $analysis->setCompanyName($request->getCompanyName() ?? $this->faker->randomElement($this->companyNames));
        $analysis->setResponsiblePerson($request->getRecipientName());
        $analysis->setContactEmail($request->getRecipientEmail());
        $analysis->setContactPhone($this->faker->phoneNumber());
        
        // Address
        $analysis->setCompanyAddress(
            $this->faker->streetAddress() . "\n" .
            $this->faker->postcode() . ' ' . $this->faker->city()
        );

        // Activity sector and codes
        $analysis->setActivitySector($this->faker->randomElement($this->activitySectors));
        
        // NAF code (70% chance)
        if ($this->faker->boolean(70)) {
            $analysis->setNafCode($this->faker->numerify('####') . $this->faker->randomLetter());
        }

        // SIRET (80% chance)
        if ($this->faker->boolean(80)) {
            $analysis->setSiret($this->faker->numerify('##############'));
        }

        // Employee count
        $employeeCounts = [5, 12, 25, 50, 100, 250, 500, 1000];
        $analysis->setEmployeeCount($this->faker->randomElement($employeeCounts));

        // OPCO (60% chance)
        if ($this->faker->boolean(60)) {
            $analysis->setOpco($this->faker->randomElement($this->opcoNames));
        }

        // Trainees information (1-8 trainees)
        $traineesCount = $this->faker->numberBetween(1, 8);
        $traineesInfo = [];
        for ($i = 0; $i < $traineesCount; $i++) {
            $traineesInfo[] = [
                'first_name' => $this->faker->firstName(),
                'last_name' => $this->faker->lastName(),
                'position' => $this->faker->randomElement([
                    'Développeur',
                    'Chef de projet',
                    'Responsable commercial',
                    'Assistant administratif',
                    'Technicien',
                    'Manager',
                    'Consultant',
                    'Analyste',
                    'Coordinateur',
                    'Superviseur'
                ])
            ];
        }
        $analysis->setTraineesInfo($traineesInfo);

        // Training information
        $trainingTitle = $request->getFormation() ? 
            $request->getFormation()->getTitle() : 
            $this->faker->randomElement($this->trainingTitles);
        $analysis->setTrainingTitle($trainingTitle);

        $analysis->setTrainingDurationHours($this->faker->randomElement([14, 21, 28, 35, 42, 56, 70]));

        // Preferred dates (70% chance)
        if ($this->faker->boolean(70)) {
            $startDate = $this->faker->dateTimeBetween('+1 month', '+6 months');
            $analysis->setPreferredStartDate($startDate);
            
            if ($this->faker->boolean(80)) {
                $endDate = (clone $startDate)->modify('+' . $this->faker->numberBetween(2, 12) . ' weeks');
                $analysis->setPreferredEndDate($endDate);
            }
        }

        // Training location preference
        $locationPreferences = ['on_site', 'remote', 'hybrid', 'training_center'];
        $analysis->setTrainingLocationPreference($this->faker->randomElement($locationPreferences));

        // Location appropriation needs (40% chance)
        if ($this->faker->boolean(40)) {
            $needs = [
                'Salle de formation équipée avec vidéoprojecteur et tableau',
                'Accès WiFi pour tous les participants',
                'Matériel informatique adapté (ordinateurs portables)',
                'Espace de pause et restauration sur site',
                'Parking disponible pour les participants',
                'Accessibilité PMR requise'
            ];
            $analysis->setLocationAppropriationNeeds($this->faker->randomElement($needs));
        }

        // Disability accommodations (20% chance)
        if ($this->faker->boolean(20)) {
            $accommodations = [
                'Adaptation pour personne malentendante (interprète LSF)',
                'Supports en gros caractères pour déficient visuel',
                'Accessibilité fauteuil roulant',
                'Adaptation du rythme de formation',
                'Supports numériques adaptés'
            ];
            $analysis->setDisabilityAccommodations($this->faker->randomElement($accommodations));
        }

        // Training expectations
        $expectations = [
            'Améliorer les compétences techniques de nos équipes pour répondre aux nouveaux défis du marché. Nous souhaitons que nos collaborateurs acquièrent une expertise pratique immédiatement applicable dans leurs missions quotidiennes.',
            'Développer l\'autonomie de nos salariés sur les outils numériques et les nouvelles technologies. L\'objectif est de moderniser nos processus internes et d\'améliorer notre productivité.',
            'Renforcer les compétences managériales de nos responsables d\'équipe. Nous attendons une amélioration de la communication interne et de la gestion des projets transversaux.',
            'Former nos commerciaux aux nouvelles techniques de vente digitale et à l\'utilisation des réseaux sociaux professionnels pour développer notre chiffre d\'affaires.',
            'Mettre à niveau nos équipes sur les réglementations en vigueur et les bonnes pratiques de notre secteur d\'activité pour maintenir notre certification qualité.'
        ];
        $analysis->setTrainingExpectations($this->faker->randomElement($expectations));

        // Specific needs
        $specificNeeds = [
            'Formation adaptée au niveau débutant avec beaucoup de pratique et d\'exemples concrets. Prévoir un support de cours détaillé.',
            'Besoin d\'une formation intensive sur une courte période pour minimiser l\'impact sur l\'activité. Privilégier les ateliers pratiques.',
            'Formation personnalisée selon les spécificités de notre secteur d\'activité. Utilisation de nos propres cas d\'usage si possible.',
            'Accompagnement post-formation souhaité pour la mise en application. Prévoir des sessions de suivi à 3 et 6 mois.',
            'Formation en petit groupe pour favoriser les échanges et l\'interactivité. Maximum 8 participants par session.'
        ];
        $analysis->setSpecificNeeds($this->faker->randomElement($specificNeeds));

        // Set submission date
        $submittedAt = $request->getCompletedAt() ?? new \DateTimeImmutable();
        $analysis->setSubmittedAt($submittedAt);

        return $analysis;
    }

    /**
     * Create an individual needs analysis with realistic data
     */
    private function createIndividualNeedsAnalysis(NeedsAnalysisRequest $request): IndividualNeedsAnalysis
    {
        $analysis = new IndividualNeedsAnalysis();
        $analysis->setNeedsAnalysisRequest($request);

        // Personal information
        $nameParts = explode(' ', $request->getRecipientName(), 2);
        $analysis->setFirstName($nameParts[0]);
        $analysis->setLastName($nameParts[1] ?? $this->faker->lastName());
        
        $analysis->setEmail($request->getRecipientEmail());
        $analysis->setPhone($this->faker->phoneNumber());
        
        // Address
        $analysis->setAddress(
            $this->faker->streetAddress() . "\n" .
            $this->faker->postcode() . ' ' . $this->faker->city()
        );

        // Professional status
        $statuses = [
            IndividualNeedsAnalysis::STATUS_EMPLOYEE,
            IndividualNeedsAnalysis::STATUS_JOB_SEEKER,
            IndividualNeedsAnalysis::STATUS_OTHER
        ];
        $status = $this->faker->randomElement($statuses);
        $analysis->setStatus($status);

        // Status details for "other"
        if ($status === IndividualNeedsAnalysis::STATUS_OTHER) {
            $otherStatuses = [
                'Dirigeant d\'entreprise',
                'Travailleur indépendant',
                'Consultant freelance',
                'Étudiant en reconversion',
                'Retraité actif'
            ];
            $analysis->setStatusOtherDetails($this->faker->randomElement($otherStatuses));
        }

        // Funding type
        $fundingTypes = [
            IndividualNeedsAnalysis::FUNDING_CPF,
            IndividualNeedsAnalysis::FUNDING_POLE_EMPLOI,
            IndividualNeedsAnalysis::FUNDING_PERSONAL,
            IndividualNeedsAnalysis::FUNDING_OTHER
        ];
        $fundingType = $this->faker->randomElement($fundingTypes);
        $analysis->setFundingType($fundingType);

        // Funding details for "other"
        if ($fundingType === IndividualNeedsAnalysis::FUNDING_OTHER) {
            $otherFundings = [
                'Financement entreprise',
                'OPCO personnel',
                'Région/Département',
                'Fonds de formation professionnelle',
                'Aide spécifique handicap'
            ];
            $analysis->setFundingOtherDetails($this->faker->randomElement($otherFundings));
        }

        // Training information
        $trainingTitle = $request->getFormation() ? 
            $request->getFormation()->getTitle() : 
            $this->faker->randomElement($this->trainingTitles);
        $analysis->setDesiredTrainingTitle($trainingTitle);

        // Professional objectives
        $objectives = [
            'Acquérir de nouvelles compétences pour évoluer vers un poste de responsable dans mon entreprise actuelle. Je souhaite développer mes capacités de management et de gestion d\'équipe.',
            'Me reconvertir professionnellement vers le secteur du numérique. Mon objectif est de devenir développeur web et travailler dans une entreprise innovante.',
            'Améliorer mes compétences en langues étrangères pour pouvoir travailler à l\'international et développer ma carrière dans une entreprise multinationale.',
            'Développer mon expertise technique pour créer ma propre entreprise de conseil. Je veux acquérir les compétences nécessaires pour être autonome.',
            'Retrouver un emploi rapidement après une période de chômage. Cette formation me permettra d\'actualiser mes compétences et d\'être plus compétitif sur le marché du travail.'
        ];
        $analysis->setProfessionalObjective($this->faker->randomElement($objectives));

        // Current level
        $levels = [
            IndividualNeedsAnalysis::LEVEL_BEGINNER,
            IndividualNeedsAnalysis::LEVEL_INTERMEDIATE,
            IndividualNeedsAnalysis::LEVEL_ADVANCED
        ];
        $analysis->setCurrentLevel($this->faker->randomElement($levels));

        // Desired duration
        $analysis->setDesiredDurationHours($this->faker->randomElement([14, 21, 28, 35, 42, 56, 70]));

        // Preferred dates (60% chance)
        if ($this->faker->boolean(60)) {
            $startDate = $this->faker->dateTimeBetween('+2 weeks', '+4 months');
            $analysis->setPreferredStartDate($startDate);
            
            if ($this->faker->boolean(70)) {
                $endDate = (clone $startDate)->modify('+' . $this->faker->numberBetween(2, 8) . ' weeks');
                $analysis->setPreferredEndDate($endDate);
            }
        }

        // Training location preference
        $locationPreferences = ['remote', 'hybrid', 'training_center'];
        $analysis->setTrainingLocationPreference($this->faker->randomElement($locationPreferences));

        // Disability accommodations (15% chance)
        if ($this->faker->boolean(15)) {
            $accommodations = [
                'Besoin d\'un interprète en langue des signes',
                'Supports de cours en gros caractères',
                'Pauses fréquentes pour raisons médicales',
                'Adaptation du rythme de formation',
                'Matériel informatique adapté'
            ];
            $analysis->setDisabilityAccommodations($this->faker->randomElement($accommodations));
        }

        // Training expectations
        $expectations = [
            'J\'attends une formation pratique avec de nombreux exercices et mises en situation. Je souhaite pouvoir appliquer immédiatement ce que j\'apprends dans mon travail quotidien.',
            'Je recherche une formation complète qui me permette d\'acquérir toutes les bases nécessaires. J\'ai besoin d\'un accompagnement personnalisé car je pars de zéro.',
            'Mon objectif est d\'obtenir une certification reconnue à l\'issue de la formation. Je veux que cette formation soit un véritable atout sur mon CV.',
            'Je souhaite une formation flexible qui s\'adapte à mes contraintes professionnelles. La possibilité de suivre certains modules à distance serait un plus.',
            'J\'attends une formation de qualité avec des formateurs experts. Je veux bénéficier de leur expérience terrain et de conseils personnalisés.'
        ];
        $analysis->setTrainingExpectations($this->faker->randomElement($expectations));

        // Specific needs
        $specificNeeds = [
            'Besoin de flexibilité dans les horaires car je travaille en équipe. Préférence pour les formations en soirée ou le week-end.',
            'Formation accélérée souhaitée car j\'ai une opportunité d\'emploi qui nécessite ces compétences rapidement.',
            'Accompagnement pour la recherche d\'emploi après la formation. Aide à la rédaction de CV et préparation aux entretiens.',
            'Formation en petit groupe pour favoriser les échanges. Je préfère un environnement convivial et interactif.',
            'Suivi post-formation souhaité pour m\'assurer de la bonne mise en application des acquis dans mon nouveau poste.'
        ];
        $analysis->setSpecificNeeds($this->faker->randomElement($specificNeeds));

        // Set submission date
        $submittedAt = $request->getCompletedAt() ?? new \DateTimeImmutable();
        $analysis->setSubmittedAt($submittedAt);

        return $analysis;
    }

    /**
     * Define fixture dependencies
     */
    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            FormationFixtures::class,
            ProspectFixtures::class, // Ensure base prospects are created first
        ];
    }
}