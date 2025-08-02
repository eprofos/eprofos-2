<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Student\ExerciseSubmission;
use App\Entity\Training\Exercise;
use App\Entity\User\Student;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;

/**
 * ExerciseSubmission fixtures for EPROFOS platform.
 *
 * Creates realistic exercise submissions to demonstrate student progress tracking,
 * grading systems, and learning analytics for Qualiopi compliance.
 */
class ExerciseSubmissionFixtures extends Fixture implements DependentFixtureInterface
{
    private Generator $faker;

    public function __construct()
    {
        $this->faker = Factory::create('fr_FR');
    }

    /**
     * Load exercise submission fixtures.
     */
    public function load(ObjectManager $manager): void
    {
        $exercises = $manager->getRepository(Exercise::class)->findAll();
        $students = $manager->getRepository(Student::class)->findAll();

        if (empty($exercises) || empty($students)) {
            return;
        }

        $submissionCount = 0;

        foreach ($students as $student) {
            // Each student submits 60-80% of available exercises
            $exercisesToSubmit = $this->faker->randomElements(
                $exercises,
                $this->faker->numberBetween(
                    (int) (count($exercises) * 0.6),
                    (int) (count($exercises) * 0.8),
                ),
            );

            foreach ($exercisesToSubmit as $exercise) {
                // 70% chance of single submission, 25% chance of 2 attempts, 5% chance of 3 attempts
                $attempts = $this->faker->randomElement([1, 1, 1, 1, 1, 1, 1, 2, 2, 3]);

                for ($attempt = 1; $attempt <= $attempts; $attempt++) {
                    $submission = $this->createExerciseSubmission($exercise, $student, $attempt);
                    $manager->persist($submission);
                    $submissionCount++;
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
            ExerciseFixtures::class,
            StudentFixtures::class,
        ];
    }

    /**
     * Create a single exercise submission.
     */
    private function createExerciseSubmission(Exercise $exercise, Student $student, int $attemptNumber): ExerciseSubmission
    {
        $submission = new ExerciseSubmission();
        $submission->setExercise($exercise);
        $submission->setStudent($student);
        $submission->setAttemptNumber($attemptNumber);

        // Set submission type based on exercise type
        $submissionType = $this->determineSubmissionType($exercise);
        $submission->setType($submissionType);

        // Set dates
        $startedAt = $this->faker->dateTimeBetween('-2 months', '-1 day');
        $submission->setStartedAt(DateTimeImmutable::createFromMutable($startedAt));

        // Generate submission data based on type
        $submissionData = $this->generateSubmissionData($submissionType, $exercise);
        $submission->setSubmissionData($submissionData);

        // Determine status and progression
        $statusDistribution = [
            ExerciseSubmission::STATUS_DRAFT => 0.10,       // 10%
            ExerciseSubmission::STATUS_SUBMITTED => 0.20,   // 20%
            ExerciseSubmission::STATUS_GRADED => 0.60,      // 60%
            ExerciseSubmission::STATUS_REVIEWED => 0.10,    // 10%
        ];

        // For later attempts, higher chance of being graded/reviewed
        if ($attemptNumber > 1) {
            $statusDistribution = [
                ExerciseSubmission::STATUS_DRAFT => 0.05,
                ExerciseSubmission::STATUS_SUBMITTED => 0.10,
                ExerciseSubmission::STATUS_GRADED => 0.70,
                ExerciseSubmission::STATUS_REVIEWED => 0.15,
            ];
        }

        $status = $this->faker->randomElement(array_keys($statusDistribution));
        $submission->setStatus($status);

        // Set submission date if not draft
        if ($status !== ExerciseSubmission::STATUS_DRAFT) {
            $submittedAt = $this->faker->dateTimeBetween($startedAt, 'now');
            $submission->setSubmittedAt(DateTimeImmutable::createFromMutable($submittedAt));
        }

        // Set grading information if graded or reviewed
        if (in_array($status, [ExerciseSubmission::STATUS_GRADED, ExerciseSubmission::STATUS_REVIEWED], true)) {
            $this->addGradingInformation($submission, $exercise, $attemptNumber);

            $gradedAt = $submission->getSubmittedAt()
                ? $this->faker->dateTimeBetween($submission->getSubmittedAt()->format('Y-m-d H:i:s'), 'now')
                : $this->faker->dateTimeBetween('-1 week', 'now');
            $submission->setGradedAt(DateTimeImmutable::createFromMutable($gradedAt));
        }

        // Set time spent
        $timeSpent = $this->faker->numberBetween(15, 180); // 15 minutes to 3 hours
        $submission->setTimeSpentMinutes($timeSpent);

        return $submission;
    }

    /**
     * Determine submission type based on exercise characteristics.
     */
    private function determineSubmissionType(Exercise $exercise): string
    {
        $title = strtolower($exercise->getTitle());
        $description = strtolower($exercise->getDescription() ?? '');

        // Check for keywords to determine type
        if (str_contains($title, 'pratique') || str_contains($description, 'pratique')
            || str_contains($title, 'manipulation') || str_contains($description, 'exercice')) {
            return ExerciseSubmission::TYPE_PRACTICAL;
        }

        if (str_contains($title, 'fichier') || str_contains($description, 'document')
            || str_contains($title, 'upload') || str_contains($description, 'télécharger')) {
            return ExerciseSubmission::TYPE_FILE;
        }

        return ExerciseSubmission::TYPE_TEXT;
    }

    /**
     * Generate submission data based on type.
     */
    private function generateSubmissionData(string $type, Exercise $exercise): array
    {
        switch ($type) {
            case ExerciseSubmission::TYPE_TEXT:
                return $this->generateTextSubmissionData();

            case ExerciseSubmission::TYPE_FILE:
                return $this->generateFileSubmissionData();

            case ExerciseSubmission::TYPE_PRACTICAL:
                return $this->generatePracticalSubmissionData();

            default:
                return [];
        }
    }

    /**
     * Generate text submission data.
     */
    private function generateTextSubmissionData(): array
    {
        $textResponses = [
            'J\'ai étudié les concepts présentés dans le cours et voici ma compréhension des éléments clés. Les méthodes abordées permettent d\'améliorer l\'efficacité du processus en optimisant les étapes critiques.',
            'Après analyse du problème posé, j\'ai identifié plusieurs solutions possibles. La première approche consiste à utiliser la méthode directe, tandis que la seconde privilégie une approche progressive.',
            'Mon analyse de la situation révèle que l\'approche recommandée dans le cours est effectivement la plus adaptée. J\'ai testé différentes variantes et les résultats confirment la théorie.',
            'L\'exercice m\'a permis de mettre en pratique les notions théoriques. J\'ai rencontré quelques difficultés au début, mais en appliquant méthodiquement les étapes, j\'ai réussi à résoudre le problème.',
            'Voici ma réponse détaillée à l\'exercice proposé. J\'ai structuré ma démarche en plusieurs phases pour bien comprendre chaque aspect du problème et proposer une solution cohérente.',
        ];

        $content = $this->faker->randomElement($textResponses);

        // Sometimes add more detailed content
        if ($this->faker->boolean(40)) {
            $additionalContent = [
                ' En complément, j\'ai approfondi certains aspects en consultant des ressources supplémentaires.',
                ' Cette expérience m\'a donné une meilleure compréhension des enjeux pratiques.',
                ' J\'ai également identifié des points d\'amélioration pour optimiser le processus.',
                ' Les résultats obtenus dépassent mes attentes initiales.',
            ];
            $content .= $this->faker->randomElement($additionalContent);
        }

        return [
            'content' => $content,
            'word_count' => str_word_count($content),
        ];
    }

    /**
     * Generate file submission data.
     */
    private function generateFileSubmissionData(): array
    {
        $fileTypes = ['pdf', 'docx', 'xlsx', 'pptx', 'jpg', 'png', 'zip'];
        $fileNames = [
            'rapport_exercice',
            'analyse_cas_pratique',
            'presentation_projet',
            'document_synthese',
            'captures_ecran',
            'code_source',
            'schema_conception',
        ];

        $files = [];
        $fileCount = $this->faker->numberBetween(1, 3);

        for ($i = 0; $i < $fileCount; $i++) {
            $fileName = $this->faker->randomElement($fileNames) . '_' . $this->faker->numberBetween(1, 999);
            $fileExtension = $this->faker->randomElement($fileTypes);

            $files[] = [
                'name' => $fileName . '.' . $fileExtension,
                'size' => $this->faker->numberBetween(50000, 5000000), // 50KB to 5MB
                'type' => $this->getMimeType($fileExtension),
                'uploaded_at' => $this->faker->dateTimeBetween('-1 week', 'now')->format('Y-m-d H:i:s'),
            ];
        }

        $descriptions = [
            'Voici les documents demandés pour cet exercice.',
            'J\'ai joint mon analyse complète avec les captures d\'écran.',
            'Le fichier contient ma solution détaillée.',
            'Vous trouverez en pièce jointe le travail réalisé.',
            'J\'ai organisé les documents par ordre de priorité.',
        ];

        return [
            'files' => $files,
            'description' => $this->faker->randomElement($descriptions),
        ];
    }

    /**
     * Generate practical submission data.
     */
    private function generatePracticalSubmissionData(): array
    {
        $checklistItems = [
            'Préparation de l\'environnement de travail',
            'Installation des outils nécessaires',
            'Configuration des paramètres de base',
            'Exécution des étapes principales',
            'Tests de validation',
            'Documentation de la procédure',
            'Vérification des résultats',
            'Nettoyage et finalisation',
        ];

        $checklist = [];
        foreach ($checklistItems as $index => $item) {
            $isCompleted = $this->faker->boolean(85); // 85% chance of completion
            $checklist[$index] = [
                'item' => $item,
                'completed' => $isCompleted,
                'completed_at' => $isCompleted ? $this->faker->dateTimeBetween('-1 week', 'now')->format('Y-m-d H:i:s') : null,
                'notes' => $isCompleted ? $this->faker->optional(0.3)->sentence() : null,
            ];
        }

        $evidenceItems = [
            'Capture d\'écran du résultat final',
            'Log des opérations effectuées',
            'Photo de la configuration matérielle',
            'Export des données de test',
            'Fichier de configuration sauvegardé',
        ];

        $evidence = [];
        $evidenceCount = $this->faker->numberBetween(2, 4);
        for ($i = 0; $i < $evidenceCount; $i++) {
            $evidence[] = [
                'type' => $this->faker->randomElement(['screenshot', 'file', 'photo', 'log']),
                'description' => $this->faker->randomElement($evidenceItems),
                'filename' => 'evidence_' . ($i + 1) . '.jpg',
            ];
        }

        $selfAssessment = [
            'difficulty_level' => $this->faker->numberBetween(1, 5),
            'confidence_level' => $this->faker->numberBetween(1, 5),
            'time_estimation' => $this->faker->randomElement(['Plus rapide que prévu', 'Comme prévu', 'Plus long que prévu']),
            'main_challenges' => $this->faker->optional(0.7)->sentence(),
            'lessons_learned' => $this->faker->optional(0.6)->sentence(),
        ];

        return [
            'checklist' => $checklist,
            'evidence' => $evidence,
            'self_assessment' => $selfAssessment,
        ];
    }

    /**
     * Add grading information to submission.
     */
    private function addGradingInformation(ExerciseSubmission $submission, Exercise $exercise, int $attemptNumber): void
    {
        $maxPoints = $exercise->getMaxPoints() ?? 20;
        $passingPoints = $exercise->getPassingPoints() ?? (int) ($maxPoints * 0.6);

        // Better scores on later attempts
        $baseScore = $attemptNumber === 1
            ? $this->faker->numberBetween((int) ($maxPoints * 0.4), $maxPoints)
            : $this->faker->numberBetween((int) ($maxPoints * 0.6), $maxPoints);

        $submission->setScore($baseScore);
        $submission->setPassed($baseScore >= $passingPoints);

        // Add feedback
        if ($submission->isPassed()) {
            $positiveFeedback = [
                'Excellent travail ! Vous maîtrisez bien les concepts abordés.',
                'Très bonne analyse et application pratique des notions du cours.',
                'Travail de qualité avec une approche méthodique remarquable.',
                'Félicitations pour cette réalisation complète et soignée.',
                'Parfait ! Vous avez su appliquer efficacement la théorie.',
            ];
            $feedback = $this->faker->randomElement($positiveFeedback);
        } else {
            $constructiveFeedback = [
                'Bon début, mais quelques points nécessitent un approfondissement.',
                'L\'approche est correcte, mais attention aux détails d\'exécution.',
                'Travail satisfaisant avec des améliorations possibles.',
                'Bonne compréhension générale, continuez vos efforts.',
                'Quelques ajustements nécessaires pour optimiser le résultat.',
            ];
            $feedback = $this->faker->randomElement($constructiveFeedback);
        }

        // Add specific suggestions for improvement
        if ($this->faker->boolean(60)) {
            $suggestions = [
                ' Pensez à vérifier la cohérence des données.',
                ' N\'hésitez pas à consulter les ressources complémentaires.',
                ' La documentation pourrait être plus détaillée.',
                ' Excellent ! Continuez dans cette voie.',
                ' Variez les exemples pour enrichir votre analyse.',
            ];
            $feedback .= $this->faker->randomElement($suggestions);
        }

        $submission->setFeedback($feedback);

        // Sometimes add auto-score and manual adjustments
        if ($this->faker->boolean(30)) {
            $autoScore = $this->faker->numberBetween($baseScore - 3, $baseScore + 2);
            $submission->setAutoScore($autoScore);

            if ($autoScore !== $baseScore) {
                $submission->setManualScore($baseScore - $autoScore);
            }
        }
    }

    /**
     * Get MIME type for file extension.
     */
    private function getMimeType(string $extension): string
    {
        return match ($extension) {
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'zip' => 'application/zip',
            default => 'application/octet-stream',
        };
    }
}
