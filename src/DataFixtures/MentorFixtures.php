<?php

namespace App\DataFixtures;

use App\Entity\User\Mentor;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Faker\Factory;

class MentorFixtures extends Fixture
{
    public const MENTOR_REFERENCE = 'mentor_';
    
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // Création de 10 mentors avec des données réalistes
        $companies = [
            ['name' => 'TechCorp Solutions', 'siret' => '12345678901234', 'sector' => 'Informatique'],
            ['name' => 'Digital Innovate', 'siret' => '23456789012345', 'sector' => 'Numérique'],
            ['name' => 'Marketing Plus', 'siret' => '34567890123456', 'sector' => 'Marketing'],
            ['name' => 'Finance Expert', 'siret' => '45678901234567', 'sector' => 'Finance'],
            ['name' => 'Design Studio', 'siret' => '56789012345678', 'sector' => 'Design'],
            ['name' => 'Commerce Online', 'siret' => '67890123456789', 'sector' => 'E-commerce'],
            ['name' => 'Consulting Pro', 'siret' => '78901234567890', 'sector' => 'Conseil'],
            ['name' => 'Data Analytics', 'siret' => '89012345678901', 'sector' => 'Data Science'],
            ['name' => 'Web Agency', 'siret' => '90123456789012', 'sector' => 'Web'],
            ['name' => 'Mobile Dev', 'siret' => '01234567890123', 'sector' => 'Mobile'],
        ];

        $positions = [
            'Développeur Senior',
            'Chef de Projet',
            'Directeur Technique',
            'Responsable Marketing',
            'Manager Commercial',
            'Consultant Senior',
            'Lead Developer',
            'Product Owner',
            'Scrum Master',
            'Architecte Solution'
        ];

        $expertiseDomains = [
            ['Développement Web', 'PHP', 'Symfony'],
            ['JavaScript', 'React', 'Node.js'],
            ['Marketing Digital', 'SEO', 'Analytics'],
            ['Gestion de Projet', 'Agile', 'Scrum'],
            ['Design UX/UI', 'Figma', 'Adobe'],
            ['E-commerce', 'Shopify', 'WooCommerce'],
            ['Data Science', 'Python', 'Machine Learning'],
            ['DevOps', 'Docker', 'AWS'],
            ['Mobile', 'React Native', 'Flutter'],
            ['Finance', 'Comptabilité', 'Analyse Financière']
        ];

        $educationLevels = [
            'bac+2',
            'bac+3',
            'bac+5',
            'bac+8'
        ];

        for ($i = 0; $i < 10; $i++) {
            $mentor = new Mentor();
            $company = $companies[$i];
            
            // Informations personnelles
            $mentor->setFirstName($faker->firstName())
                   ->setLastName($faker->lastName())
                   ->setEmail($faker->unique()->safeEmail())
                   ->setPhone($faker->phoneNumber());

            // Mot de passe hashé (password123)
            $hashedPassword = $this->passwordHasher->hashPassword($mentor, 'password123');
            $mentor->setPassword($hashedPassword);

            // Informations professionnelles
            $mentor->setPosition($positions[$i])
                   ->setCompanyName($company['name'])
                   ->setCompanySiret($company['siret'])
                   ->setExperienceYears($faker->numberBetween(3, 15))
                   ->setEducationLevel($faker->randomElement($educationLevels))
                   ->setExpertiseDomains($expertiseDomains[$i]);

            // Status et vérification
            $mentor->setIsActive(true)
                   ->setEmailVerified($faker->boolean(85)) // 85% des mentors ont vérifié leur email
                   ->setCreatedAt(new \DateTimeImmutable($faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d H:i:s')))
                   ->setUpdatedAt(new \DateTimeImmutable());

            // Quelques mentors ont une dernière connexion
            if ($faker->boolean(70)) {
                $mentor->setLastLoginAt(new \DateTimeImmutable($faker->dateTimeBetween('-1 month', 'now')->format('Y-m-d H:i:s')));
            }

            $manager->persist($mentor);
            
            // Créer une référence pour pouvoir lier les mentors à d'autres entités plus tard
            $this->addReference(self::MENTOR_REFERENCE . $i, $mentor);
        }

        // Créer un mentor de test avec des identifiants connus
        $testMentor = new Mentor();
        $testMentor->setFirstName('Jean')
                   ->setLastName('Dupont')
                   ->setEmail('mentor@eprofos.fr')
                   ->setPhone('+33 6 12 34 56 78')
                   ->setPosition('Directeur Technique')
                   ->setCompanyName('EPROFOS Corp')
                   ->setCompanySiret('12345678901234')
                   ->setExperienceYears(10)
                   ->setEducationLevel('bac+5')
                   ->setExpertiseDomains(['Développement Web', 'PHP', 'Symfony', 'Management'])
                   ->setIsActive(true)
                   ->setEmailVerified(true)
                   ->setCreatedAt(new \DateTimeImmutable('-3 months'))
                   ->setUpdatedAt(new \DateTimeImmutable())
                   ->setLastLoginAt(new \DateTimeImmutable('-1 day'));

        $hashedPassword = $this->passwordHasher->hashPassword($testMentor, 'password123');
        $testMentor->setPassword($hashedPassword);

        $manager->persist($testMentor);
        $this->addReference(self::MENTOR_REFERENCE . 'test', $testMentor);

        $manager->flush();
    }
}
