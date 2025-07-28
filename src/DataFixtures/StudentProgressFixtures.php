<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Core\StudentProgress;
use App\Entity\Training\Chapter;
use App\Entity\Training\Formation;
use App\Entity\Training\Module;
use App\Entity\User\Student;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

/**
 * StudentProgressFixtures - Generate realistic student progress data for Qualiopi Criterion 12 testing.
 *
 * Creates comprehensive progress tracking records with varied engagement levels,
 * completion rates, and risk factors to test the dropout prevention system.
 */
class StudentProgressFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // Get all students and formations from existing fixtures
        $students = $manager->getRepository(Student::class)->findAll();
        $formations = $manager->getRepository(Formation::class)->findAll();
        $modules = $manager->getRepository(Module::class)->findAll();
        $chapters = $manager->getRepository(Chapter::class)->findAll();

        if (empty($students) || empty($formations)) {
            echo "⚠️  Warning: No students or formations found. Make sure to load StudentFixtures and FormationFixtures first.\n";

            return;
        }

        $progressCount = 0;
        $atRiskCount = 0;

        // Create progress records for students
        foreach ($students as $student) {
            // Each student is enrolled in 1-3 formations
            $enrollmentCount = $faker->numberBetween(1, min(3, count($formations)));
            $enrolledFormations = $faker->randomElements($formations, $enrollmentCount);

            foreach ($enrolledFormations as $formation) {
                $progress = $this->createStudentProgress($student, $formation, $modules, $chapters, $faker);
                $manager->persist($progress);

                $progressCount++;

                if ($progress->isAtRiskOfDropout()) {
                    $atRiskCount++;
                }
            }
        }

        $manager->flush();

        echo "✅ Student Progress: Created {$progressCount} progress records\n";
        echo "⚠️  At Risk: {$atRiskCount} students identified as at risk of dropout\n";
    }

    public function getDependencies(): array
    {
        return [
            StudentFixtures::class,
            FormationFixtures::class,
            ModuleFixtures::class,
            ChapterFixtures::class,
        ];
    }

    private function createStudentProgress(
        Student $student,
        Formation $formation,
        array $modules,
        array $chapters,
        $faker,
    ): StudentProgress {
        $progress = new StudentProgress();
        $progress->setStudent($student);
        $progress->setFormation($formation);

        // Determine student profile (affects all metrics)
        $profileType = $faker->randomElement([
            'excellent',    // 15% - High performers
            'good',         // 35% - Good students
            'average',      // 30% - Average students
            'struggling',   // 15% - Students with difficulties
            'at_risk',       // 5% - High risk of dropout
        ]);

        // Set start date (1-90 days ago)
        $daysRunning = $faker->numberBetween(1, 90);
        $startDate = new DateTime("-{$daysRunning} days");
        $progress->setStartedAt($startDate);

        // Generate metrics based on profile
        $this->setProgressMetrics($progress, $profileType, $daysRunning, $faker);

        // Set current module and chapter
        $this->setCurrentProgress($progress, $formation, $modules, $chapters, $faker);

        // Calculate engagement score and detect risks
        $progress->calculateEngagementScore();
        $progress->detectRiskSignals();

        return $progress;
    }

    private function setProgressMetrics(StudentProgress $progress, string $profileType, int $daysRunning, $faker): void
    {
        switch ($profileType) {
            case 'excellent':
                // High performers
                $completion = $faker->numberBetween(70, 100);
                $attendanceRate = $faker->numberBetween(90, 100);
                $loginFrequency = 0.8; // Login almost daily
                $sessionLength = $faker->numberBetween(45, 120); // Long study sessions
                $inactivityDays = $faker->numberBetween(0, 2); // Very active
                $missedSessions = $faker->numberBetween(0, 1);
                break;

            case 'good':
                // Good students
                $completion = $faker->numberBetween(50, 85);
                $attendanceRate = $faker->numberBetween(80, 95);
                $loginFrequency = 0.6; // Regular login
                $sessionLength = $faker->numberBetween(30, 90);
                $inactivityDays = $faker->numberBetween(0, 4);
                $missedSessions = $faker->numberBetween(0, 2);
                break;

            case 'average':
                // Average students
                $completion = $faker->numberBetween(25, 70);
                $attendanceRate = $faker->numberBetween(70, 85);
                $loginFrequency = 0.4; // Moderate login
                $sessionLength = $faker->numberBetween(20, 60);
                $inactivityDays = $faker->numberBetween(1, 7);
                $missedSessions = $faker->numberBetween(1, 3);
                break;

            case 'struggling':
                // Students with difficulties
                $completion = $faker->numberBetween(10, 45);
                $attendanceRate = $faker->numberBetween(60, 80);
                $loginFrequency = 0.25; // Infrequent login
                $sessionLength = $faker->numberBetween(15, 45);
                $inactivityDays = $faker->numberBetween(3, 14);
                $missedSessions = $faker->numberBetween(2, 5);
                break;

            case 'at_risk':
                // High risk of dropout
                $completion = $faker->numberBetween(0, 25);
                $attendanceRate = $faker->numberBetween(30, 70);
                $loginFrequency = 0.1; // Very rare login
                $sessionLength = $faker->numberBetween(10, 30);
                $inactivityDays = $faker->numberBetween(7, 30);
                $missedSessions = $faker->numberBetween(3, 8);
                break;
        }

        // Set completion percentage
        $progress->setCompletionPercentage((string) $completion);

        // Set attendance rate
        $progress->setAttendanceRate((string) $attendanceRate);

        // Set login and activity data
        $expectedLogins = (int) ($daysRunning * $loginFrequency);
        $loginCount = max(1, $faker->numberBetween((int) ($expectedLogins * 0.7), (int) ($expectedLogins * 1.3)));
        $progress->setLoginCount($loginCount);

        // Calculate total time spent
        $totalTimeSpent = $loginCount * $sessionLength;
        $progress->setTotalTimeSpent($totalTimeSpent);

        // Set last activity
        $lastActivity = new DateTime("-{$inactivityDays} days");
        $progress->setLastActivity($lastActivity);

        // Set missed sessions
        $progress->setMissedSessions($missedSessions);

        // Generate module and chapter progress
        $this->generateDetailedProgress($progress, $completion, $faker);

        // Mark as completed if 100%
        if ($completion >= 100) {
            /** @var DateTimeImmutable $completedDate */
            $completedDate = clone $progress->getStartedAt();
            $completedDate->add(new DateInterval("P{$daysRunning}D"));
            $progress->setCompletedAt($completedDate);
        }
    }

    private function generateDetailedProgress(StudentProgress $progress, int $overallCompletion, $faker): void
    {
        // Generate module progress (simulated - would need actual module data)
        $moduleCount = $faker->numberBetween(3, 6);
        $moduleProgress = [];

        for ($i = 1; $i <= $moduleCount; $i++) {
            $moduleId = "module_{$i}";

            // Module completion based on overall progress
            if ($overallCompletion >= ($i * 100 / $moduleCount)) {
                $moduleCompletion = 100;
                $completed = true;
                $completedAt = $faker->dateTimeBetween($progress->getStartedAt(), 'now');
            } else {
                $moduleCompletion = $faker->numberBetween(0, 80);
                $completed = false;
                $completedAt = null;
            }

            $moduleProgress[$moduleId] = [
                'completed' => $completed,
                'percentage' => $moduleCompletion,
                'lastUpdated' => (new DateTime())->format('Y-m-d H:i:s'),
            ];

            if ($completed && $completedAt) {
                $moduleProgress[$moduleId]['completedAt'] = $completedAt->format('Y-m-d H:i:s');
            }
        }

        $progress->setModuleProgress($moduleProgress);

        // Generate chapter progress
        $chapterCount = $faker->numberBetween(8, 15);
        $chapterProgress = [];

        for ($i = 1; $i <= $chapterCount; $i++) {
            $chapterId = "chapter_{$i}";

            // Chapter completion based on overall progress
            if ($overallCompletion >= ($i * 100 / $chapterCount)) {
                $chapterCompletion = 100;
                $completed = true;
                $completedAt = $faker->dateTimeBetween($progress->getStartedAt(), 'now');
            } else {
                $chapterCompletion = $faker->numberBetween(0, 90);
                $completed = false;
                $completedAt = null;
            }

            $chapterProgress[$chapterId] = [
                'completed' => $completed,
                'percentage' => $chapterCompletion,
                'lastUpdated' => (new DateTime())->format('Y-m-d H:i:s'),
            ];

            if ($completed && $completedAt) {
                $chapterProgress[$chapterId]['completedAt'] = $completedAt->format('Y-m-d H:i:s');
            }
        }

        $progress->setChapterProgress($chapterProgress);
    }

    private function setCurrentProgress(
        StudentProgress $progress,
        Formation $formation,
        array $modules,
        array $chapters,
        $faker,
    ): void {
        // Find modules and chapters related to this formation
        $formationModules = array_filter($modules, static fn ($module) => $module->getFormation() === $formation);
        $formationChapters = array_filter($chapters, static fn ($chapter) => in_array($chapter->getModule(), $formationModules, true));

        // Set current module and chapter based on progress
        $completion = (float) $progress->getCompletionPercentage();

        if (!empty($formationModules) && $completion < 100) {
            // Set current module based on progress
            $moduleIndex = (int) (($completion / 100) * count($formationModules));
            $moduleIndex = min($moduleIndex, count($formationModules) - 1);
            $currentModule = array_values($formationModules)[$moduleIndex] ?? null;

            if ($currentModule) {
                $progress->setCurrentModule($currentModule);

                // Set current chapter within the current module
                $moduleChapters = array_filter($formationChapters, static fn ($chapter) => $chapter->getModule() === $currentModule);
                if (!empty($moduleChapters)) {
                    $chapterIndex = $faker->numberBetween(0, count($moduleChapters) - 1);
                    $currentChapter = array_values($moduleChapters)[$chapterIndex] ?? null;
                    if ($currentChapter) {
                        $progress->setCurrentChapter($currentChapter);
                    }
                }
            }
        }
    }
}
