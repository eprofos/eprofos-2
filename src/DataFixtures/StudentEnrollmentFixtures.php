<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Core\StudentEnrollment;
use App\Entity\Core\StudentProgress;
use App\Entity\Training\SessionRegistration;
use App\Entity\User\Student;
use DateTime;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

/**
 * StudentEnrollmentFixtures for creating realistic enrollment test data.
 *
 * Creates enrollments linking Students to confirmed SessionRegistrations
 * with appropriate StudentProgress records for content access testing.
 */
class StudentEnrollmentFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // Get existing data
        $students = $manager->getRepository(Student::class)->findAll();
        $sessionRegistrations = $manager->getRepository(SessionRegistration::class)->findBy(['status' => 'confirmed']);
        $studentProgresses = $manager->getRepository(StudentProgress::class)->findAll();

        if (empty($students) || empty($sessionRegistrations)) {
            return;
        }

        $enrollmentCount = 0;
        $createdEnrollments = [];

        // Create enrollments for students with matching email addresses
        foreach ($students as $student) {
            $matchingRegistrations = array_filter(
                $sessionRegistrations,
                static fn (SessionRegistration $registration) => $registration->getEmail() === $student->getEmail(),
            );

            foreach ($matchingRegistrations as $registration) {
                $enrollment = $this->createEnrollment($student, $registration, $studentProgresses, $faker);
                $manager->persist($enrollment);
                $createdEnrollments[] = $enrollment;
                $enrollmentCount++;
            }
        }

        // Create additional random enrollments for students without matching registrations
        $studentsWithoutEnrollments = array_filter(
            $students,
            static function (Student $student) use ($createdEnrollments) {
                foreach ($createdEnrollments as $enrollment) {
                    if ($enrollment->getStudent() === $student) {
                        return false;
                    }
                }

                return true;
            },
        );

        // Ensure every student has at least one enrollment
        foreach ($studentsWithoutEnrollments as $student) {
            if ($enrollmentCount >= count($sessionRegistrations)) {
                break;
            }

            // Find available registrations (not already used)
            $availableRegistrations = array_filter(
                $sessionRegistrations,
                static function (SessionRegistration $registration) use ($createdEnrollments) {
                    foreach ($createdEnrollments as $enrollment) {
                        if ($enrollment->getSessionRegistration() === $registration) {
                            return false;
                        }
                    }

                    return true;
                },
            );

            if (!empty($availableRegistrations)) {
                $randomRegistration = $faker->randomElement($availableRegistrations);

                // Update registration email to match student for realism
                $randomRegistration->setEmail($student->getEmail());
                $randomRegistration->setFirstName($student->getFirstName());
                $randomRegistration->setLastName($student->getLastName());

                $enrollment = $this->createEnrollment($student, $randomRegistration, $studentProgresses, $faker);
                $manager->persist($enrollment);
                $createdEnrollments[] = $enrollment;
                $enrollmentCount++;
            }
        }

        $manager->flush();

        // Log statistics
        $statusCounts = [];
        foreach ($createdEnrollments as $enrollment) {
            $status = $enrollment->getStatus();
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        }
    }

    public function getDependencies(): array
    {
        return [
            StudentFixtures::class,
            SessionFixtures::class, // This creates SessionRegistration entities
            StudentProgressFixtures::class,
        ];
    }

    private function createEnrollment(
        Student $student,
        SessionRegistration $registration,
        array $studentProgresses,
        $faker,
    ): StudentEnrollment {
        $enrollment = new StudentEnrollment();
        $enrollment->setStudent($student);
        $enrollment->setSessionRegistration($registration);

        // Set enrollment status based on realistic distribution
        $status = $this->generateEnrollmentStatus($faker);
        $enrollment->setStatus($status);

        // Set enrollment date (usually close to registration confirmation)
        if ($registration->getConfirmedAt()) {
            $confirmedAt = $registration->getConfirmedAt();
            $daysOffset = $faker->numberBetween(0, 3); // Enrolled within 3 days of confirmation

            if ($confirmedAt instanceof DateTimeImmutable) {
                $enrollment->setEnrolledAt($confirmedAt->modify("+{$daysOffset} days"));
            } elseif ($confirmedAt instanceof DateTime) {
                $mutableDate = clone $confirmedAt;
                $mutableDate->modify("+{$daysOffset} days");
                $enrollment->setEnrolledAt(DateTimeImmutable::createFromMutable($mutableDate));
            }
        }

        // Set completion date if completed
        if ($status === StudentEnrollment::STATUS_COMPLETED) {
            $session = $registration->getSession();
            if ($session && $session->getEndDate()) {
                $endDate = $session->getEndDate();
                $daysOffset = $faker->numberBetween(0, 7); // Completed within a week of session end

                if ($endDate instanceof DateTimeImmutable) {
                    $enrollment->setCompletedAt($endDate->modify("+{$daysOffset} days"));
                } elseif ($endDate instanceof DateTime) {
                    $mutableDate = clone $endDate;
                    $mutableDate->modify("+{$daysOffset} days");
                    $enrollment->setCompletedAt(DateTimeImmutable::createFromMutable($mutableDate));
                }
            }
        }

        // Set dropout reason if dropped out
        if ($status === StudentEnrollment::STATUS_DROPPED_OUT) {
            $enrollment->setDropoutReason($faker->randomElement([
                'Contraintes professionnelles',
                'Problèmes personnels',
                'Formation ne correspondant pas aux attentes',
                'Changement de situation professionnelle',
                'Difficultés financières',
                'Problèmes de santé',
                'Manque de temps',
                'Formation trop difficile',
            ]));
        }

        // Set enrollment source
        $enrollment->setEnrollmentSource($faker->randomElement([
            'manual',
            'automatic',
            'bulk_import',
            'migration',
            'admin_creation',
        ]));

        // Add admin notes occasionally
        if ($faker->boolean(30)) {
            $enrollment->setAdminNotes($faker->sentence());
        }

        // Find or create associated StudentProgress
        $formation = $registration->getSession()?->getFormation();
        if ($formation) {
            $existingProgress = $this->findStudentProgress($student, $formation, $studentProgresses);
            if ($existingProgress) {
                $enrollment->setProgress($existingProgress);
            }
        }

        // Add metadata
        $metadata = [
            'registration_type' => $faker->randomElement(['individual', 'company', 'cpf']),
            'payment_method' => $faker->randomElement(['credit_card', 'bank_transfer', 'cpf', 'company_budget']),
            'marketing_source' => $faker->randomElement(['website', 'phone', 'email', 'referral', 'advertisement']),
        ];

        if ($faker->boolean(20)) {
            $metadata['special_needs'] = $faker->randomElement([
                'accessibility_required',
                'dietary_restrictions',
                'schedule_flexibility',
                'remote_access_needed',
            ]);
        }

        $enrollment->setMetadata($metadata);

        return $enrollment;
    }

    private function generateEnrollmentStatus($faker): string
    {
        // Realistic distribution of enrollment statuses
        return $faker->randomElement([
            StudentEnrollment::STATUS_ENROLLED,    // 60%
            StudentEnrollment::STATUS_ENROLLED,
            StudentEnrollment::STATUS_ENROLLED,
            StudentEnrollment::STATUS_ENROLLED,
            StudentEnrollment::STATUS_ENROLLED,
            StudentEnrollment::STATUS_ENROLLED,
            StudentEnrollment::STATUS_COMPLETED,   // 25%
            StudentEnrollment::STATUS_COMPLETED,
            StudentEnrollment::STATUS_COMPLETED,
            StudentEnrollment::STATUS_DROPPED_OUT, // 10%
            StudentEnrollment::STATUS_SUSPENDED,   // 5%
        ]);
    }

    private function findStudentProgress(Student $student, $formation, array $studentProgresses): ?StudentProgress
    {
        foreach ($studentProgresses as $progress) {
            if ($progress->getStudent() === $student && $progress->getFormation() === $formation) {
                return $progress;
            }
        }

        return null;
    }
}
