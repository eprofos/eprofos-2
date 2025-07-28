<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User\Teacher;
use DateTime;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Teacher Fixtures.
 *
 * Creates sample teacher data for testing and development.
 * Generates realistic teacher profiles with various specialties,
 * experience levels, and professional backgrounds.
 */
class TeacherFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // Define realistic teaching specialties for professional training
        $specialties = [
            'Informatique et Digital',
            'Management et Leadership',
            'Communication et Marketing',
            'Ressources Humaines',
            'Finance et Comptabilité',
            'Qualité et Certification',
            'Logistique et Supply Chain',
            'Développement Personnel',
            'Langues Étrangères',
            'Sécurité et Prévention',
            'Environnement et Développement Durable',
            'Innovation et Créativité',
            'Vente et Négociation',
            'Gestion de Projet',
            'Formation et Pédagogie',
        ];

        // Define professional titles
        $titles = [
            'M.', 'Mme', 'Dr', 'Prof.', 'Ing.',
        ];

        // Define qualifications examples
        $qualifications = [
            'Master en Sciences de l\'Éducation',
            'Doctorat en Informatique',
            'MBA Management',
            'Certification PMP',
            'Master en Ressources Humaines',
            'Ingénieur Grande École',
            'Master en Communication',
            'Certification Scrum Master',
            'Master en Finance',
            'Certification ITIL',
            'DEA en Sciences Sociales',
            'Master en Marketing Digital',
            'Certification Six Sigma',
            'Master en Psychologie du Travail',
            'Doctorat en Sciences de Gestion',
        ];

        // Create main test teacher
        $teacher = new Teacher();
        $teacher->setEmail('teacher@eprofos.com');
        $teacher->setFirstName('Marie');
        $teacher->setLastName('Dubois');
        $teacher->setTitle('Dr');
        $teacher->setPhone('01 23 45 67 90');
        $teacher->setBirthDate(new DateTime('1975-08-20'));
        $teacher->setAddress('45 avenue des Formateurs');
        $teacher->setPostalCode('75008');
        $teacher->setCity('Paris');
        $teacher->setCountry('France');
        $teacher->setSpecialty('Informatique et Digital');
        $teacher->setQualifications('Doctorat en Informatique, Certification PMP');
        $teacher->setYearsOfExperience(15);
        $teacher->setBiography('Formatrice experte en transformation digitale avec plus de 15 ans d\'expérience dans le secteur IT. Spécialisée dans l\'accompagnement des entreprises vers la digitalisation et la formation aux nouvelles technologies.');
        $teacher->setEmailVerified(true);
        $teacher->setEmailVerifiedAt(new DateTimeImmutable('-2 weeks'));

        $hashedPassword = $this->passwordHasher->hashPassword($teacher, 'password');
        $teacher->setPassword($hashedPassword);

        $manager->persist($teacher);
        $this->addReference('teacher-main', $teacher);

        // Create additional experienced teachers
        for ($i = 1; $i <= 15; $i++) {
            $teacher = new Teacher();
            $teacher->setEmail($faker->unique()->email);
            $teacher->setFirstName($faker->firstName);
            $teacher->setLastName($faker->lastName);
            $teacher->setTitle($faker->optional(0.7)->randomElement($titles));
            $teacher->setPhone($faker->optional(0.9)->phoneNumber);
            $teacher->setBirthDate($faker->optional(0.9)->dateTimeBetween('-65 years', '-30 years'));
            $teacher->setAddress($faker->optional(0.8)->streetAddress);
            $teacher->setPostalCode($faker->optional(0.8)->postcode);
            $teacher->setCity($faker->optional(0.8)->city);
            $teacher->setCountry($faker->optional(0.6)->country);
            $teacher->setSpecialty($faker->randomElement($specialties));
            $teacher->setQualifications($faker->optional(0.9)->randomElement($qualifications));
            $teacher->setYearsOfExperience($faker->numberBetween(3, 30));

            // Generate realistic biography
            $experience = $teacher->getYearsOfExperience();
            $specialty = $teacher->getSpecialty();
            $biographies = [
                "Formateur expert en {$specialty} avec {$experience} ans d'expérience. Accompagne les entreprises dans leur développement et la montée en compétences de leurs équipes.",
                "Spécialiste en {$specialty}, fort de {$experience} années d'expérience professionnelle. Passionné par la transmission de savoir et l'innovation pédagogique.",
                "Consultant-formateur en {$specialty} depuis {$experience} ans. Intervient auprès de grandes entreprises et PME pour optimiser leurs processus et former leurs collaborateurs.",
                "Expert en {$specialty} avec {$experience} ans d'expérience terrain. Développe des formations sur-mesure adaptées aux besoins spécifiques de chaque organisation.",
                "Formateur senior en {$specialty}, {$experience} ans d'expérience dans l'accompagnement des entreprises. Spécialisé dans la formation continue et le développement professionnel.",
            ];
            $teacher->setBiography($faker->optional(0.8)->randomElement($biographies));

            // Random verification status (most teachers should be verified)
            $isVerified = $faker->boolean(90); // 90% chance of being verified
            $teacher->setEmailVerified($isVerified);
            if ($isVerified) {
                $teacher->setEmailVerifiedAt(new DateTimeImmutable($faker->dateTimeBetween('-1 year', '-1 day')->format('Y-m-d H:i:s')));
            }

            // Random activity status (most teachers should be active)
            $teacher->setIsActive($faker->boolean(95)); // 95% chance of being active

            $hashedPassword = $this->passwordHasher->hashPassword($teacher, 'password');
            $teacher->setPassword($hashedPassword);

            $manager->persist($teacher);
            $this->addReference('teacher-' . $i, $teacher);
        }

        // Create some teachers with specific specialties for testing
        $specializedTeachers = [
            [
                'email' => 'formateur.it@eprofos.com',
                'firstName' => 'Thomas',
                'lastName' => 'Bernard',
                'title' => 'Ing.',
                'specialty' => 'Informatique et Digital',
                'qualifications' => 'Ingénieur Grande École, Certification AWS',
                'experience' => 12,
                'biography' => 'Ingénieur et formateur spécialisé dans les technologies cloud et le développement web. Expert en transformation digitale des entreprises.',
            ],
            [
                'email' => 'formatrice.management@eprofos.com',
                'firstName' => 'Sophie',
                'lastName' => 'Martin',
                'title' => 'Mme',
                'specialty' => 'Management et Leadership',
                'qualifications' => 'MBA Management, Certification Coach Professionnel',
                'experience' => 18,
                'biography' => 'Coach et formatrice en management avec une expertise reconnue dans le développement du leadership et la gestion d\'équipe.',
            ],
            [
                'email' => 'formateur.rh@eprofos.com',
                'firstName' => 'Philippe',
                'lastName' => 'Rousseau',
                'title' => 'M.',
                'specialty' => 'Ressources Humaines',
                'qualifications' => 'Master en Ressources Humaines, Certification GPEC',
                'experience' => 20,
                'biography' => 'Expert RH avec 20 ans d\'expérience. Spécialisé dans la gestion des talents, le recrutement et la formation professionnelle.',
            ],
        ];

        foreach ($specializedTeachers as $teacherData) {
            $teacher = new Teacher();
            $teacher->setEmail($teacherData['email']);
            $teacher->setFirstName($teacherData['firstName']);
            $teacher->setLastName($teacherData['lastName']);
            $teacher->setTitle($teacherData['title']);
            $teacher->setPhone($faker->phoneNumber);
            $teacher->setBirthDate($faker->dateTimeBetween('-55 years', '-35 years'));
            $teacher->setAddress($faker->streetAddress);
            $teacher->setPostalCode($faker->postcode);
            $teacher->setCity($faker->city);
            $teacher->setCountry('France');
            $teacher->setSpecialty($teacherData['specialty']);
            $teacher->setQualifications($teacherData['qualifications']);
            $teacher->setYearsOfExperience($teacherData['experience']);
            $teacher->setBiography($teacherData['biography']);
            $teacher->setEmailVerified(true);
            $teacher->setEmailVerifiedAt(new DateTimeImmutable('-1 month'));

            $hashedPassword = $this->passwordHasher->hashPassword($teacher, 'password');
            $teacher->setPassword($hashedPassword);

            $manager->persist($teacher);
        }

        // Create some teachers with unverified emails for testing
        for ($i = 1; $i <= 3; $i++) {
            $teacher = new Teacher();
            $teacher->setEmail($faker->unique()->email);
            $teacher->setFirstName($faker->firstName);
            $teacher->setLastName($faker->lastName);
            $teacher->setTitle($faker->optional(0.7)->randomElement($titles));
            $teacher->setPhone($faker->optional(0.9)->phoneNumber);
            $teacher->setBirthDate($faker->optional(0.9)->dateTimeBetween('-65 years', '-30 years'));
            $teacher->setAddress($faker->optional(0.8)->streetAddress);
            $teacher->setPostalCode($faker->optional(0.8)->postcode);
            $teacher->setCity($faker->optional(0.8)->city);
            $teacher->setCountry($faker->optional(0.6)->country);
            $teacher->setSpecialty($faker->randomElement($specialties));
            $teacher->setQualifications($faker->optional(0.9)->randomElement($qualifications));
            $teacher->setYearsOfExperience($faker->numberBetween(2, 25));

            // Unverified email
            $teacher->setEmailVerified(false);
            $teacher->generateEmailVerificationToken();

            $hashedPassword = $this->passwordHasher->hashPassword($teacher, 'password');
            $teacher->setPassword($hashedPassword);

            $manager->persist($teacher);
            $this->addReference('teacher-unverified-' . $i, $teacher);
        }

        // Create some inactive teachers for testing
        for ($i = 1; $i <= 2; $i++) {
            $teacher = new Teacher();
            $teacher->setEmail($faker->unique()->email);
            $teacher->setFirstName($faker->firstName);
            $teacher->setLastName($faker->lastName);
            $teacher->setTitle($faker->optional(0.7)->randomElement($titles));
            $teacher->setPhone($faker->optional(0.9)->phoneNumber);
            $teacher->setBirthDate($faker->optional(0.9)->dateTimeBetween('-65 years', '-30 years'));
            $teacher->setAddress($faker->optional(0.8)->streetAddress);
            $teacher->setPostalCode($faker->optional(0.8)->postcode);
            $teacher->setCity($faker->optional(0.8)->city);
            $teacher->setCountry($faker->optional(0.6)->country);
            $teacher->setSpecialty($faker->randomElement($specialties));
            $teacher->setQualifications($faker->optional(0.9)->randomElement($qualifications));
            $teacher->setYearsOfExperience($faker->numberBetween(5, 30));

            // Inactive teacher
            $teacher->setIsActive(false);
            $teacher->setEmailVerified(true);
            $teacher->setEmailVerifiedAt(new DateTimeImmutable($faker->dateTimeBetween('-1 year', '-1 month')->format('Y-m-d H:i:s')));

            $hashedPassword = $this->passwordHasher->hashPassword($teacher, 'password');
            $teacher->setPassword($hashedPassword);

            $manager->persist($teacher);
            $this->addReference('teacher-inactive-' . $i, $teacher);
        }

        // Create some junior teachers (recent graduates or career changers)
        for ($i = 1; $i <= 5; $i++) {
            $teacher = new Teacher();
            $teacher->setEmail($faker->unique()->email);
            $teacher->setFirstName($faker->firstName);
            $teacher->setLastName($faker->lastName);
            $teacher->setTitle($faker->optional(0.5)->randomElement(['M.', 'Mme']));
            $teacher->setPhone($faker->optional(0.9)->phoneNumber);
            $teacher->setBirthDate($faker->dateTimeBetween('-35 years', '-25 years'));
            $teacher->setAddress($faker->optional(0.8)->streetAddress);
            $teacher->setPostalCode($faker->optional(0.8)->postcode);
            $teacher->setCity($faker->optional(0.8)->city);
            $teacher->setCountry($faker->optional(0.6)->country);
            $teacher->setSpecialty($faker->randomElement($specialties));
            $teacher->setQualifications($faker->randomElement([
                'Master en Sciences de l\'Éducation',
                'Master spécialisé',
                'Certification professionnelle',
                'Formation continue certifiante',
            ]));
            $teacher->setYearsOfExperience($faker->numberBetween(1, 3));

            $experience = $teacher->getYearsOfExperience();
            $specialty = $teacher->getSpecialty();
            $teacher->setBiography("Jeune formateur en {$specialty} avec {$experience} an(s) d'expérience. Passionné par l'innovation pédagogique et les nouvelles méthodes d'apprentissage.");

            $teacher->setEmailVerified(true);
            $teacher->setEmailVerifiedAt(new DateTimeImmutable($faker->dateTimeBetween('-6 months', '-1 week')->format('Y-m-d H:i:s')));

            $hashedPassword = $this->passwordHasher->hashPassword($teacher, 'password');
            $teacher->setPassword($hashedPassword);

            $manager->persist($teacher);
            $this->addReference('teacher-junior-' . $i, $teacher);
        }

        $manager->flush();
    }
}
