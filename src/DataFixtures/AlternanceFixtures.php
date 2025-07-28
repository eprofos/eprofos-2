<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Alternance\AlternanceContract;
use App\Entity\Alternance\AlternanceProgram;
use App\Entity\Training\Session;
use App\Entity\User\Mentor;
use App\Entity\User\Student;
use App\Entity\User\Teacher;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class AlternanceFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // Get alternance sessions and users
        $alternanceSessions = $manager->getRepository(Session::class)->findBy(['isAlternanceSession' => true]);
        $students = $manager->getRepository(Student::class)->findAll();
        $mentors = $manager->getRepository(Mentor::class)->findAll();
        $teachers = $manager->getRepository(Teacher::class)->findAll();

        if (empty($alternanceSessions)) {
            echo "⚠️  No alternance sessions found. Please run SessionFixtures first.\n";

            return;
        }

        $contractCount = 0;
        $programCount = 0;

        foreach ($alternanceSessions as $session) {
            // Create 1-3 contracts per alternance session
            $contractsToCreate = $faker->numberBetween(1, 3);

            for ($i = 0; $i < $contractsToCreate; $i++) {
                $contract = new AlternanceContract();

                // Basic relationships
                $contract->setStudent($faker->randomElement($students));
                $contract->setSession($session);

                if (!empty($mentors)) {
                    $contract->setMentor($faker->randomElement($mentors));
                }

                if (!empty($teachers)) {
                    $contract->setPedagogicalSupervisor($faker->randomElement($teachers));
                }

                // Contract Number - Generate realistic contract number
                $year = date('Y');
                $contractNumber = 'ALT-' . $year . '-' . str_pad((string)($contractCount + 1), 4, '0', STR_PAD_LEFT);
                $contract->setContractNumber($contractNumber);

                // Company Information
                $companies = [
                    'TechCorp Solutions',
                    'Digital Innovation SARL',
                    'WebDev Entreprise',
                    'Agence Numérique Plus',
                    'Systèmes Informatiques Pro',
                    'CreaTech Industries',
                    'DataFlow Solutions',
                    'CloudTech Services',
                    'DevOps Consulting',
                    'UX Design Studio',
                ];
                $contract->setCompanyName($faker->randomElement($companies));

                $contract->setCompanyAddress(
                    $faker->streetAddress . "\n" .
                    $faker->postcode . ' ' . $faker->city,
                );

                $contract->setCompanySiret($faker->regexify('[0-9]{14}'));

                // Company Contact Information
                $contract->setCompanyContactPerson($faker->firstName . ' ' . $faker->lastName);
                $contract->setCompanyContactEmail($faker->companyEmail);
                $contract->setCompanyContactPhone($faker->phoneNumber);

                // Contract Details
                $contractTypes = ['apprentissage', 'professionnalisation', 'stage_alterné'];
                $contract->setContractType($faker->randomElement($contractTypes));

                // Dates based on session dates
                $sessionStart = $session->getStartDate();
                $sessionEnd = $session->getEndDate();

                $contract->setStartDate($sessionStart);
                $contract->setEndDate($sessionEnd);

                // Duration - Calculate from dates (in months)
                $duration = $sessionStart->diff($sessionEnd)->m + ($sessionStart->diff($sessionEnd)->y * 12);
                $contract->setDuration($duration ?: $faker->numberBetween(6, 24));

                // Job Information
                $jobTitles = [
                    'Développeur Web Junior',
                    'Assistant Administrateur Système',
                    'Chargé de Communication Digitale',
                    'Analyste Développeur',
                    'Technicien Support Informatique',
                    'Designer UX/UI Junior',
                    'Gestionnaire de Projet Digital',
                    'Développeur Mobile Junior',
                    'Consultant IT Junior',
                    'Data Analyst Junior',
                ];
                $contract->setJobTitle($faker->randomElement($jobTitles));

                $jobDescriptions = [
                    'Participation au développement d\'applications web sous supervision du tuteur entreprise.',
                    'Support technique et maintenance des systèmes informatiques de l\'entreprise.',
                    'Création de contenus digitaux et gestion des réseaux sociaux de l\'entreprise.',
                    'Analyse des besoins utilisateurs et développement de solutions logicielles.',
                    'Assistance technique aux utilisateurs et maintenance du parc informatique.',
                    'Conception d\'interfaces utilisateur et amélioration de l\'expérience client.',
                    'Coordination de projets numériques et suivi des développements.',
                    'Développement d\'applications mobiles et intégration d\'APIs.',
                    'Conseil technique auprès des clients et support avant-vente.',
                    'Analyse de données et création de tableaux de bord métier.',
                ];
                $contract->setJobDescription($faker->randomElement($jobDescriptions));

                // Learning and Company Objectives
                $learningObjectives = [
                    'Maîtriser les langages de programmation web modernes',
                    'Développer des compétences en gestion de projet',
                    'Acquérir une expertise en sécurité informatique',
                    'Apprendre les méthodologies agiles',
                    'Comprendre les enjeux de l\'UX/UI design',
                ];
                $contract->setLearningObjectives($faker->randomElements($learningObjectives, $faker->numberBetween(2, 4)));

                $companyObjectives = [
                    'Former un futur collaborateur aux méthodes de l\'entreprise',
                    'Contribuer à l\'innovation technologique de l\'équipe',
                    'Apporter un regard neuf sur les processus existants',
                    'Renforcer l\'équipe de développement',
                    'Développer une expertise métier spécifique',
                ];
                $contract->setCompanyObjectives($faker->randomElements($companyObjectives, $faker->numberBetween(1, 3)));

                // Objectives, Tasks, and Evaluation Criteria (new text fields)
                $objectivesTexts = [
                    'Acquérir une expérience professionnelle significative dans le domaine du développement web tout en développant des compétences techniques et relationnelles.',
                    'Maîtriser les outils et méthodologies de développement utilisés en entreprise et contribuer activement aux projets de l\'équipe.',
                    'Développer une expertise technique solide et une compréhension approfondie du secteur d\'activité de l\'entreprise d\'accueil.',
                    'Consolider les acquis théoriques par une mise en pratique concrète et progressive des compétences professionnelles.',
                ];
                $contract->setObjectives($faker->randomElement($objectivesTexts));

                $tasksTexts = [
                    'Participation au développement d\'applications web sous la supervision du tuteur entreprise. Maintenance et évolution des sites existants. Contribution aux tests et à la documentation technique.',
                    'Support technique niveau 1 et 2 auprès des utilisateurs. Maintenance préventive et corrective du parc informatique. Participation aux projets d\'infrastructure.',
                    'Création de contenus digitaux pour les réseaux sociaux et le site web. Analyse des performances des campagnes marketing. Assistance dans la gestion de la relation client.',
                    'Développement de fonctionnalités spécifiques selon les besoins métier. Participation aux réunions d\'équipe et aux phases de conception. Veille technologique et proposition d\'améliorations.',
                ];
                $contract->setTasks($faker->randomElement($tasksTexts));

                $evaluationCriteriaTexts = [
                    'Respect des délais et qualité du code produit. Capacité d\'adaptation et d\'apprentissage. Intégration dans l\'équipe et communication professionnelle.',
                    'Autonomie progressive dans la résolution des problèmes techniques. Qualité du support apporté aux utilisateurs. Respect des procédures et protocoles de sécurité.',
                    'Créativité et pertinence des contenus produits. Capacité d\'analyse des résultats marketing. Relationnel client et représentation de l\'entreprise.',
                    'Innovation et force de proposition. Maîtrise des outils et technologies utilisés. Contribution aux objectifs de l\'équipe et de l\'entreprise.',
                ];
                $contract->setEvaluationCriteria($faker->randomElement($evaluationCriteriaTexts));

                // Working Hours and Compensation
                $contract->setWeeklyCenterHours($faker->numberBetween(8, 16));
                $contract->setWeeklyCompanyHours($faker->numberBetween(19, 27));

                // Compensation (in euros per month)
                $compensationRanges = [
                    'apprentissage' => [600, 1200],
                    'professionnalisation' => [800, 1500],
                    'stage_alterné' => [400, 800],
                ];
                $contractType = $contract->getContractType();
                $compensationRange = $compensationRanges[$contractType] ?? [600, 1200];
                $contract->setCompensation($faker->numberBetween($compensationRange[0], $compensationRange[1]));

                // Remuneration
                $remunerations = [
                    '55% du SMIC',
                    '800€ net par mois',
                    '65% du SMIC',
                    '900€ net par mois',
                    '70% du SMIC',
                    'Selon convention collective',
                ];
                $contract->setRemuneration($faker->randomElement($remunerations));

                // Status
                $statuses = ['draft', 'pending_signature', 'signed', 'active', 'completed'];
                $statusWeights = [10, 15, 25, 40, 10]; // Most are active
                $status = $faker->randomElement(array_merge(
                    array_fill(0, $statusWeights[0], $statuses[0]),
                    array_fill(0, $statusWeights[1], $statuses[1]),
                    array_fill(0, $statusWeights[2], $statuses[2]),
                    array_fill(0, $statusWeights[3], $statuses[3]),
                    array_fill(0, $statusWeights[4], $statuses[4]),
                ));
                $contract->setStatus($status);

                // Notes
                if ($faker->boolean(40)) {
                    $notes = [
                        'Candidat très motivé avec un projet professionnel clair',
                        'Bonne adaptation à l\'équipe, progression satisfaisante',
                        'Besoin d\'accompagnement renforcé sur certains aspects techniques',
                        'Excellente implication dans les projets de l\'entreprise',
                        'Suivi particulier requis pour la partie communication',
                        'Candidat autonome avec de bonnes capacités d\'apprentissage',
                    ];
                    $contract->setNotes($faker->randomElement($notes));
                }

                // Additional Data
                $additionalData = [
                    'transport' => $faker->randomElement(['Véhicule personnel', 'Transport en commun', 'Vélo', 'Covoiturage']),
                    'dietary_requirements' => $faker->boolean(20) ? 'Régime alimentaire spécifique' : null,
                    'emergency_contact' => $faker->name . ' - ' . $faker->phoneNumber,
                    'previous_experience' => $faker->boolean(60) ? 'Stage de ' . $faker->numberBetween(1, 6) . ' mois' : 'Aucune expérience professionnelle',
                ];
                $contract->setAdditionalData(array_filter($additionalData));

                $manager->persist($contract);
                $contractCount++;
            }

            // Create an AlternanceProgram for this session
            $program = new AlternanceProgram();
            $program->setSession($session);

            // Program Details
            $programTitles = [
                'Programme Développeur Web Full-Stack en Alternance',
                'Cursus Administrateur Systèmes et Réseaux',
                'Formation Digital Marketing & Communication',
                'Programme Analyste Développeur',
                'Cursus Support Informatique et Maintenance',
                'Formation UX/UI Designer Digital',
                'Programme Chef de Projet Digital',
                'Cursus Développeur Mobile Cross-Platform',
                'Formation Consultant IT & Cyber-sécurité',
                'Programme Data Analyst & Business Intelligence',
            ];
            $program->setTitle($faker->randomElement($programTitles));

            $descriptions = [
                'Programme complet alliant formation théorique en centre et expérience pratique en entreprise pour former des professionnels opérationnels.',
                'Cursus intensif mêlant apprentissage technique et immersion professionnelle pour développer une expertise métier reconnue.',
                'Formation professionnalisante combinant modules pédagogiques innovants et missions concrètes en environnement professionnel.',
            ];
            $program->setDescription($faker->randomElement($descriptions));

            // Duration Management
            $totalWeeks = $faker->numberBetween(26, 104); // 6 months to 2 years
            $centerWeeks = (int) ($totalWeeks * 0.3); // ~30% in center
            $companyWeeks = $totalWeeks - $centerWeeks;

            $program->setTotalDuration($totalWeeks);
            $program->setCenterDuration($centerWeeks);
            $program->setCompanyDuration($companyWeeks);

            // Modules for center and company
            $centerModulesSets = [
                [
                    'Module 1: Fondamentaux du développement web',
                    'Module 2: Frameworks et technologies avancées',
                    'Module 3: Gestion de projet et méthodologies agiles',
                ],
                [
                    'Module 1: Administration systèmes Linux/Windows',
                    'Module 2: Réseaux et infrastructures',
                    'Module 3: Virtualisation et cloud computing',
                ],
                [
                    'Module 1: Communication digitale et stratégies',
                    'Module 2: Outils de création et design',
                    'Module 3: Analyse de données et ROI',
                ],
            ];
            $program->setCenterModules($faker->randomElement($centerModulesSets));

            $companyModulesSets = [
                [
                    'Mission 1: Intégration et découverte de l\'environnement professionnel',
                    'Mission 2: Participation aux projets de développement',
                    'Mission 3: Prise d\'autonomie sur des tâches spécifiques',
                ],
                [
                    'Mission 1: Support utilisateur et maintenance',
                    'Mission 2: Administration système et réseau',
                    'Mission 3: Projets d\'infrastructure',
                ],
                [
                    'Mission 1: Création de contenus et community management',
                    'Mission 2: Campagnes marketing et analyse',
                    'Mission 3: Stratégie digitale et pilotage',
                ],
            ];
            $program->setCompanyModules($faker->randomElement($companyModulesSets));

            // Coordination Points
            $coordinationPoints = [
                'Réunion de coordination mensuelle centre-entreprise',
                'Visite du référent pédagogique en entreprise',
                'Point d\'étape trimestriel avec le tuteur',
                'Suivi hebdomadaire par carnet de liaison',
                'Entretien individuel bimensuel',
                'Restitution de projet en milieu de parcours',
            ];
            $program->setCoordinationPoints($faker->randomElements($coordinationPoints, $faker->numberBetween(3, 5)));

            // Assessment
            $assessmentPeriods = [
                'Évaluation mensuelle des acquis',
                'Bilan trimestriel avec jury',
                'Soutenance semestrielle',
                'Évaluation finale en fin de parcours',
            ];
            $program->setAssessmentPeriods($faker->randomElements($assessmentPeriods, $faker->numberBetween(2, 4)));

            // Rhythm
            $rhythms = [
                '2 jours centre / 3 jours entreprise par semaine',
                '1 semaine centre / 3 semaines entreprise',
                'Alternance par blocs de 2 semaines',
                'Rythme adaptatif selon projets',
            ];
            $program->setRhythm($faker->randomElement($rhythms));

            // Learning Progression
            $learningProgression = [
                'Phase 1: Acquisition des fondamentaux (25%)',
                'Phase 2: Mise en pratique accompagnée (50%)',
                'Phase 3: Autonomie et expertise (25%)',
            ];
            $program->setLearningProgression($learningProgression);

            // Additional Information in notes
            $additionalInfo = [
                'Programme adapté aux besoins spécifiques du secteur d\'activité.',
                'Partenariat privilégié avec des entreprises leaders du domaine.',
                'Formation certifiante avec reconnaissance professionnelle.',
                'Accompagnement personnalisé selon le profil de l\'alternant.',
            ];
            if ($faker->boolean(60)) {
                $program->setNotes($faker->randomElement($additionalInfo));
            }

            // Additional Data for all the extra information
            $additionalData = [
                'assessment_methods' => $faker->randomElement([
                    'Évaluation continue en centre de formation et contrôle continu des connaissances.',
                    'Évaluation des compétences professionnelles en situation de travail par le tuteur entreprise.',
                    'Projets pratiques et soutenances devant un jury de professionnels.',
                ]),
                'success_criteria' => $faker->randomElement([
                    'Validation de tous les modules de formation avec note minimale de 12/20.',
                    'Évaluation positive du tuteur entreprise sur les compétences professionnelles.',
                    'Réalisation et soutenance réussie du projet final.',
                ]),
                'professional_objectives' => $faker->randomElement([
                    'Insertion professionnelle immédiate dans l\'entreprise d\'accueil ou secteur équivalent.',
                    'Acquisition d\'une certification professionnelle reconnue par la branche.',
                    'Développement d\'un réseau professionnel et de compétences transversales.',
                ]),
                'progress_tracking' => $faker->randomElement([
                    'Livret de suivi numérique partagé entre centre, entreprise et alternant.',
                    'Entretiens réguliers avec le référent pédagogique et évaluations formatives.',
                    'Grille de compétences mise à jour en continu et objectifs personnalisés.',
                ]),
                'resources' => $faker->randomElement([
                    'Plateforme e-learning avec cours et exercices interactifs.',
                    'Accès aux outils professionnels et licences logicielles.',
                    'Documentation technique et veille technologique.',
                ]),
                'skills' => $faker->randomElement([
                    ['HTML5, CSS3, JavaScript', 'PHP, Python ou Node.js', 'Framework (Symfony, React, Vue.js)'],
                    ['Administration Linux/Windows Server', 'Configuration réseaux TCP/IP', 'Virtualisation'],
                    ['Communication digitale', 'Outils de création', 'Analyse de données'],
                ]),
            ];
            $program->setAdditionalData($additionalData);

            $manager->persist($program);
            $programCount++;
        }

        $manager->flush();

        echo "✅ Alternance: Created {$contractCount} contracts and {$programCount} programs\n";
    }

    public function getDependencies(): array
    {
        return [
            SessionFixtures::class,
            UserFixtures::class,
            TeacherFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['alternance'];
    }
}
