<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Core\StudentEnrollment;
use App\Entity\Student\Certificate;
use App\Service\Student\CertificateService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

/**
 * CertificateFixtures for creating realistic certificate test data.
 *
 * Creates certificates for completed student enrollments to test
 * the certificate system functionality.
 */
class CertificateFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly CertificateService $certificateService
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // Get completed enrollments
        $completedEnrollments = $manager->getRepository(StudentEnrollment::class)
            ->findBy(['status' => StudentEnrollment::STATUS_COMPLETED]);

        if (empty($completedEnrollments)) {
            return;
        }

        $generatedCount = 0;

        // Generate certificates for a subset of completed enrollments
        foreach ($completedEnrollments as $enrollment) {
            // Only generate for some enrollments to simulate realistic scenario
            if ($faker->boolean(60)) { // 60% chance of having a certificate
                try {
                    // Check if certificate already exists
                    $student = $enrollment->getStudent();
                    $formation = $enrollment->getSessionRegistration()?->getSession()?->getFormation();
                    
                    if (!$student || !$formation) {
                        continue;
                    }

                    $existingCertificate = $manager->getRepository(Certificate::class)
                        ->findOneBy([
                            'student' => $student,
                            'formation' => $formation,
                        ]);

                    if ($existingCertificate) {
                        continue;
                    }

                    // Create certificate manually for fixtures (bypass eligibility check)
                    $certificate = new Certificate();
                    $certificate->setStudent($student);
                    $certificate->setFormation($formation);
                    $certificate->setEnrollment($enrollment);
                    $certificate->setCertificateTemplate('formation_completion_2024');

                    // Generate realistic completion data
                    $finalScore = $faker->numberBetween(60, 100);
                    $certificate->setFinalScore((string) $finalScore);
                    $certificate->calculateGrade();

                    $completionData = [
                        'completion_date' => $enrollment->getCompletedAt()?->format('c'),
                        'total_hours' => $formation->getDurationHours() ?? $faker->numberBetween(14, 35),
                        'final_score' => $finalScore,
                        'attendance_rate' => $faker->numberBetween(80, 100),
                        'modules_completed' => count($formation->getActiveModules()),
                        'qcm_count' => $faker->numberBetween(3, 8),
                        'exercise_count' => $faker->numberBetween(5, 12),
                        'average_qcm_score' => $faker->numberBetween(65, 95),
                        'average_exercise_score' => $faker->numberBetween(70, 98),
                    ];

                    $certificate->setCompletionData($completionData);

                    // Add metadata
                    $metadata = [
                        'formation_duration' => $formation->getDurationHours(),
                        'formation_category' => $formation->getCategory()?->getName(),
                        'session_id' => $enrollment->getSessionRegistration()?->getSession()?->getId(),
                        'generated_by' => 'fixtures',
                        'generation_timestamp' => $certificate->getIssuedAt()->format('c'),
                    ];
                    $certificate->setMetadata($metadata);

                    // Occasionally add revoked certificates
                    if ($faker->boolean(5)) { // 5% chance of revocation
                        $certificate->revoke($faker->randomElement([
                            'Erreur dans les données',
                            'Fraude détectée',
                            'Demande de l\'étudiant',
                            'Révision des critères',
                        ]));
                    }

                    $manager->persist($certificate);
                    $generatedCount++;

                    // Don't generate too many certificates in fixtures
                    if ($generatedCount >= 15) {
                        break;
                    }
                } catch (\Exception $e) {
                    // Skip this enrollment and continue
                    continue;
                }
            }
        }

        $manager->flush();

        if ($generatedCount > 0) {
            echo sprintf("Generated %d certificates for testing\n", $generatedCount);
        }
    }

    public function getDependencies(): array
    {
        return [
            StudentEnrollmentFixtures::class,
        ];
    }
}
