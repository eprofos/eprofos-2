<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User\Student;
use DateTime;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Student Fixtures.
 *
 * Creates sample student data for testing and development.
 */
class StudentFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // Create main test student
        $student = new Student();
        $student->setEmail('student@eprofos.com');
        $student->setFirstName('Jean');
        $student->setLastName('Dupont');
        $student->setPhone('01 23 45 67 89');
        $student->setBirthDate(new DateTime('1990-05-15'));
        $student->setAddress('123 rue de la Formation');
        $student->setPostalCode('75001');
        $student->setCity('Paris');
        $student->setCountry('France');
        $student->setEducationLevel('Bac+3');
        $student->setProfession('Développeur Web');
        $student->setCompany('Tech Corp');
        $student->setEmailVerified(true);
        $student->setEmailVerifiedAt(new DateTimeImmutable('-1 week'));

        $hashedPassword = $this->passwordHasher->hashPassword($student, 'password');
        $student->setPassword($hashedPassword);

        $manager->persist($student);
        $this->addReference('student-main', $student);

        // Create additional test students
        for ($i = 1; $i <= 10; $i++) {
            $student = new Student();
            $student->setEmail($faker->unique()->email);
            $student->setFirstName($faker->firstName);
            $student->setLastName($faker->lastName);
            $student->setPhone($faker->optional(0.8)->phoneNumber);
            $student->setBirthDate($faker->optional(0.9)->dateTimeBetween('-50 years', '-18 years'));
            $student->setAddress($faker->optional(0.7)->streetAddress);
            $student->setPostalCode($faker->optional(0.7)->postcode);
            $student->setCity($faker->optional(0.7)->city);
            $student->setCountry($faker->optional(0.5)->country);
            $student->setEducationLevel($faker->optional(0.6)->randomElement([
                'Bac', 'Bac+2', 'Bac+3', 'Bac+4', 'Bac+5', 'Master', 'Doctorat',
            ]));
            $student->setProfession($faker->optional(0.8)->jobTitle);
            $student->setCompany($faker->optional(0.6)->company);

            // Random verification status
            $isVerified = $faker->boolean(85); // 85% chance of being verified
            $student->setEmailVerified($isVerified);
            if ($isVerified) {
                $student->setEmailVerifiedAt(new DateTimeImmutable($faker->dateTimeBetween('-6 months', '-1 day')->format('Y-m-d H:i:s')));
            }

            // Random activity status
            $student->setIsActive($faker->boolean(95)); // 95% chance of being active

            $hashedPassword = $this->passwordHasher->hashPassword($student, 'password');
            $student->setPassword($hashedPassword);

            $manager->persist($student);
            $this->addReference('student-' . $i, $student);
        }

        // Create some students with unverified emails
        for ($i = 1; $i <= 3; $i++) {
            $student = new Student();
            $student->setEmail($faker->unique()->email);
            $student->setFirstName($faker->firstName);
            $student->setLastName($faker->lastName);
            $student->setPhone($faker->optional(0.8)->phoneNumber);
            $student->setBirthDate($faker->optional(0.9)->dateTimeBetween('-50 years', '-18 years'));
            $student->setAddress($faker->optional(0.7)->streetAddress);
            $student->setPostalCode($faker->optional(0.7)->postcode);
            $student->setCity($faker->optional(0.7)->city);
            $student->setCountry($faker->optional(0.5)->country);
            $student->setEducationLevel($faker->optional(0.6)->randomElement([
                'Bac', 'Bac+2', 'Bac+3', 'Bac+4', 'Bac+5', 'Master', 'Doctorat',
            ]));
            $student->setProfession($faker->optional(0.8)->jobTitle);
            $student->setCompany($faker->optional(0.6)->company);

            // Unverified email
            $student->setEmailVerified(false);
            $student->generateEmailVerificationToken();

            $hashedPassword = $this->passwordHasher->hashPassword($student, 'password');
            $student->setPassword($hashedPassword);

            $manager->persist($student);
            $this->addReference('student-unverified-' . $i, $student);
        }

        // Create some inactive students
        for ($i = 1; $i <= 2; $i++) {
            $student = new Student();
            $student->setEmail($faker->unique()->email);
            $student->setFirstName($faker->firstName);
            $student->setLastName($faker->lastName);
            $student->setPhone($faker->optional(0.8)->phoneNumber);
            $student->setBirthDate($faker->optional(0.9)->dateTimeBetween('-50 years', '-18 years'));
            $student->setAddress($faker->optional(0.7)->streetAddress);
            $student->setPostalCode($faker->optional(0.7)->postcode);
            $student->setCity($faker->optional(0.7)->city);
            $student->setCountry($faker->optional(0.5)->country);
            $student->setEducationLevel($faker->optional(0.6)->randomElement([
                'Bac', 'Bac+2', 'Bac+3', 'Bac+4', 'Bac+5', 'Master', 'Doctorat',
            ]));
            $student->setProfession($faker->optional(0.8)->jobTitle);
            $student->setCompany($faker->optional(0.6)->company);

            // Inactive student
            $student->setIsActive(false);
            $student->setEmailVerified(true);
            $student->setEmailVerifiedAt(new DateTimeImmutable($faker->dateTimeBetween('-6 months', '-1 day')->format('Y-m-d H:i:s')));

            $hashedPassword = $this->passwordHasher->hashPassword($student, 'password');
            $student->setPassword($hashedPassword);

            $manager->persist($student);
            $this->addReference('student-inactive-' . $i, $student);
        }

        $manager->flush();
    }
}
