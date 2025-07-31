<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Student\QCMAttempt;
use App\Entity\Training\QCM;
use App\Entity\User\Student;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use DateTimeImmutable;

/**
 * QCMAttempt fixtures for EPROFOS platform.
 *
 * Creates realistic QCM attempts to demonstrate student assessment tracking,
 * scoring systems, and learning analytics for Qualiopi compliance.
 */
class QCMAttemptFixtures extends Fixture implements DependentFixtureInterface
{
    private Generator $faker;

    public function __construct()
    {
        $this->faker = Factory::create('fr_FR');
    }

    /**
     * Load QCM attempt fixtures.
     */
    public function load(ObjectManager $manager): void
    {
        $qcms = $manager->getRepository(QCM::class)->findAll();
        $students = $manager->getRepository(Student::class)->findAll();

        if (empty($qcms) || empty($students)) {
            return;
        }

        $attemptCount = 0;

        foreach ($students as $student) {
            // Each student attempts 50-70% of available QCMs
            $qcmsToAttempt = $this->faker->randomElements(
                $qcms,
                $this->faker->numberBetween(
                    (int)(count($qcms) * 0.5),
                    (int)(count($qcms) * 0.7)
                )
            );

            foreach ($qcmsToAttempt as $qcm) {
                // 80% chance of single attempt, 15% chance of 2 attempts, 5% chance of 3 attempts
                $attempts = $this->faker->randomElement([1, 1, 1, 1, 1, 1, 1, 1, 2, 3]);

                for ($attempt = 1; $attempt <= $attempts; $attempt++) {
                    $qcmAttempt = $this->createQCMAttempt($qcm, $student, $attempt);
                    $manager->persist($qcmAttempt);
                    $attemptCount++;
                }
            }
        }

        $manager->flush();
    }

    /**
     * Define fixture dependencies.
     */
    public function getDependencies(): array
    {
        return [
            QCMFixtures::class,
            StudentFixtures::class,
        ];
    }

    /**
     * Create a single QCM attempt.
     */
    private function createQCMAttempt(QCM $qcm, Student $student, int $attemptNumber): QCMAttempt
    {
        $attempt = new QCMAttempt();
        $attempt->setQcm($qcm);
        $attempt->setStudent($student);
        $attempt->setAttemptNumber($attemptNumber);

        // Set start time
        $startedAt = $this->faker->dateTimeBetween('-2 months', '-1 day');
        $attempt->setStartedAt(DateTimeImmutable::createFromMutable($startedAt));

        // Set expiration if QCM has time limit
        if ($qcm->getTimeLimitMinutes()) {
            $expiresAt = (clone $startedAt)->modify('+' . $qcm->getTimeLimitMinutes() . ' minutes');
            $attempt->setExpiresAt(DateTimeImmutable::createFromMutable($expiresAt));
        }

        // Determine status
        $statusDistribution = [
            QCMAttempt::STATUS_COMPLETED => 0.75,     // 75%
            QCMAttempt::STATUS_IN_PROGRESS => 0.10,   // 10%
            QCMAttempt::STATUS_ABANDONED => 0.10,     // 10%
            QCMAttempt::STATUS_EXPIRED => 0.05,       // 5%
        ];

        // For later attempts, higher chance of completion
        if ($attemptNumber > 1) {
            $statusDistribution[QCMAttempt::STATUS_COMPLETED] = 0.85;
            $statusDistribution[QCMAttempt::STATUS_IN_PROGRESS] = 0.05;
            $statusDistribution[QCMAttempt::STATUS_ABANDONED] = 0.05;
            $statusDistribution[QCMAttempt::STATUS_EXPIRED] = 0.05;
        }

        $status = $this->faker->randomElement(array_keys($statusDistribution));
        $attempt->setStatus($status);

        // Generate answers and calculate score
        $this->generateAnswers($attempt, $qcm);
        $attempt->calculateScore();

        // Set completion details based on status
        switch ($status) {
            case QCMAttempt::STATUS_COMPLETED:
                $this->completeAttempt($attempt, $startedAt);
                break;

            case QCMAttempt::STATUS_ABANDONED:
                $this->abandonAttempt($attempt, $startedAt);
                break;

            case QCMAttempt::STATUS_EXPIRED:
                $this->expireAttempt($attempt);
                break;

            case QCMAttempt::STATUS_IN_PROGRESS:
                // Generate partial answers
                $this->generatePartialAnswers($attempt, $qcm);
                break;
        }

        return $attempt;
    }

    /**
     * Generate answers for a QCM attempt.
     */
    private function generateAnswers(QCMAttempt $attempt, QCM $qcm): void
    {
        $questions = $qcm->getQuestions();
        $answers = [];
        $questionScores = [];

        foreach ($questions as $index => $question) {
            $questionType = $question['type'] ?? 'single_choice';
            $choices = $question['choices'] ?? [];
            $correctAnswers = $question['correct_answers'] ?? [];
            $points = $question['points'] ?? 1;

            if (empty($choices)) {
                continue;
            }

            // Generate student answers based on question type and student performance
            $studentAnswers = $this->generateQuestionAnswer($questionType, $choices, $correctAnswers, $attempt->getAttemptNumber());
            $answers[$index] = $studentAnswers;

            // Calculate score for this question
            $questionScore = $this->calculateQuestionScore($studentAnswers, $correctAnswers, $points);
            $questionScores[$index] = [
                'correct' => $questionScore === $points,
                'points' => $questionScore,
                'max_points' => $points,
            ];
        }

        $attempt->setAnswers($answers);
        $attempt->setQuestionScores($questionScores);
    }

    /**
     * Generate partial answers for in-progress attempts.
     */
    private function generatePartialAnswers(QCMAttempt $attempt, QCM $qcm): void
    {
        $questions = $qcm->getQuestions();
        $answers = [];
        $questionsToAnswer = $this->faker->numberBetween(1, (int)(count($questions) * 0.6));

        for ($i = 0; $i < $questionsToAnswer; $i++) {
            $question = $questions[$i];
            $questionType = $question['type'] ?? 'single_choice';
            $choices = $question['choices'] ?? [];
            $correctAnswers = $question['correct_answers'] ?? [];

            if (!empty($choices)) {
                $studentAnswers = $this->generateQuestionAnswer($questionType, $choices, $correctAnswers, $attempt->getAttemptNumber());
                $answers[$i] = $studentAnswers;
            }
        }

        $attempt->setAnswers($answers);
        
        // Set time spent so far
        $timeSpentMinutes = $this->faker->numberBetween(5, 30);
        $attempt->setTimeSpent($timeSpentMinutes * 60);
    }

    /**
     * Generate answer for a single question.
     */
    private function generateQuestionAnswer(string $questionType, array $choices, array $correctAnswers, int $attemptNumber): array
    {
        $choiceIndices = array_keys($choices);
        
        // Performance improves with attempt number
        $correctnessChance = match ($attemptNumber) {
            1 => 0.65, // 65% chance of correct answer on first attempt
            2 => 0.80, // 80% chance on second attempt
            default => 0.90, // 90% chance on third+ attempt
        };

        if ($questionType === 'multiple_choice') {
            // Multiple choice questions
            if ($this->faker->randomFloat() < $correctnessChance) {
                // Give correct answer(s)
                return $correctAnswers;
            } else {
                // Give partially correct or incorrect answer
                $incorrectChoices = array_diff($choiceIndices, $correctAnswers);
                $numberOfSelections = $this->faker->numberBetween(1, min(3, count($choiceIndices)));
                
                // Mix some correct and incorrect answers
                $selectedChoices = [];
                if (!empty($correctAnswers) && $this->faker->boolean(50)) {
                    $selectedChoices[] = $this->faker->randomElement($correctAnswers);
                }
                
                while (count($selectedChoices) < $numberOfSelections && !empty($incorrectChoices)) {
                    $choice = $this->faker->randomElement($incorrectChoices);
                    if (!in_array($choice, $selectedChoices)) {
                        $selectedChoices[] = $choice;
                    }
                    $incorrectChoices = array_diff($incorrectChoices, [$choice]);
                }
                
                return $selectedChoices;
            }
        } else {
            // Single choice questions
            if ($this->faker->randomFloat() < $correctnessChance && !empty($correctAnswers)) {
                // Give correct answer
                return [$correctAnswers[0]];
            } else {
                // Give incorrect answer
                $incorrectChoices = array_diff($choiceIndices, $correctAnswers);
                if (!empty($incorrectChoices)) {
                    return [$this->faker->randomElement($incorrectChoices)];
                }
                // Fallback to random choice
                return [$this->faker->randomElement($choiceIndices)];
            }
        }
    }

    /**
     * Calculate score for a single question.
     */
    private function calculateQuestionScore(array $studentAnswers, array $correctAnswers, int $questionPoints): int
    {
        if (empty($studentAnswers)) {
            return 0;
        }

        sort($studentAnswers);
        sort($correctAnswers);

        // Exact match gives full points
        if ($studentAnswers === $correctAnswers) {
            return $questionPoints;
        }

        // Partial credit for multi-select questions
        if (count($correctAnswers) > 1) {
            $correctSelected = count(array_intersect($studentAnswers, $correctAnswers));
            $incorrectSelected = count(array_diff($studentAnswers, $correctAnswers));
            $totalCorrect = count($correctAnswers);

            if ($totalCorrect > 0) {
                $partialScore = max(0, ($correctSelected - $incorrectSelected) / $totalCorrect);
                return (int) round($partialScore * $questionPoints);
            }
        }

        return 0;
    }

    /**
     * Complete the QCM attempt.
     */
    private function completeAttempt(QCMAttempt $attempt, \DateTime $startedAt): void
    {
        $timeLimitMinutes = $attempt->getQcm()->getTimeLimitMinutes();
        $maxTimeMinutes = $timeLimitMinutes ?: 60; // Default max 1 hour
        
        // Generate realistic completion time (usually less than time limit)
        $completionTimeMinutes = $this->faker->numberBetween(
            (int)($maxTimeMinutes * 0.3),
            (int)($maxTimeMinutes * 0.8)
        );
        
        $completedAt = (clone $startedAt)->modify('+' . $completionTimeMinutes . ' minutes');
        $attempt->setCompletedAt(DateTimeImmutable::createFromMutable($completedAt));
        $attempt->setTimeSpent($completionTimeMinutes * 60);

        // Determine if passed
        $passingScore = $attempt->getQcm()->getPassingScore();
        if ($passingScore !== null) {
            $attempt->setPassed($attempt->getScore() >= $passingScore);
        }
    }

    /**
     * Abandon the QCM attempt.
     */
    private function abandonAttempt(QCMAttempt $attempt, \DateTime $startedAt): void
    {
        // Students typically abandon after 10-40% of time limit
        $timeLimitMinutes = $attempt->getQcm()->getTimeLimitMinutes() ?: 60;
        $abandonTimeMinutes = $this->faker->numberBetween(
            (int)($timeLimitMinutes * 0.1),
            (int)($timeLimitMinutes * 0.4)
        );
        
        $attempt->setTimeSpent($abandonTimeMinutes * 60);
        $attempt->setPassed(false);
    }

    /**
     * Expire the QCM attempt.
     */
    private function expireAttempt(QCMAttempt $attempt): void
    {
        if ($attempt->getExpiresAt()) {
            $timeLimit = $attempt->getQcm()->getTimeLimitMinutes() * 60; // in seconds
            $attempt->setTimeSpent($timeLimit);
        }
        $attempt->setPassed(false);
    }
}
