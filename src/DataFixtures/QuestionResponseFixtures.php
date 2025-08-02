<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Assessment\Question;
use App\Entity\Assessment\QuestionnaireResponse;
use App\Entity\Assessment\QuestionResponse;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;

/**
 * QuestionResponse fixtures for EPROFOS platform.
 *
 * Creates realistic question responses for questionnaires and assessments
 * to demonstrate the system's evaluation and analytics capabilities.
 */
class QuestionResponseFixtures extends Fixture implements DependentFixtureInterface
{
    private Generator $faker;

    public function __construct()
    {
        $this->faker = Factory::create('fr_FR');
    }

    /**
     * Load question response fixtures.
     */
    public function load(ObjectManager $manager): void
    {
        // Get existing questionnaire responses
        $questionnaireResponses = $manager->getRepository(QuestionnaireResponse::class)->findAll();

        if (empty($questionnaireResponses)) {
            return;
        }

        foreach ($questionnaireResponses as $questionnaireResponse) {
            $questionnaire = $questionnaireResponse->getQuestionnaire();

            if (!$questionnaire) {
                continue;
            }

            // Get questions for this questionnaire
            $questions = $manager->getRepository(Question::class)
                ->findBy(['questionnaire' => $questionnaire])
            ;

            foreach ($questions as $question) {
                $questionResponse = new QuestionResponse();
                $questionResponse->setQuestion($question);
                $questionResponse->setQuestionnaireResponse($questionnaireResponse);

                // Generate response based on question type
                $this->generateResponseByType($questionResponse, $question);

                // Calculate score if question has points
                if ($question->getPoints() > 0) {
                    $score = $questionResponse->calculateScore();
                    $questionResponse->setScoreEarned($score);
                }

                $manager->persist($questionResponse);
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
            QuestionnaireResponseFixtures::class,
            QuestionFixtures::class,
        ];
    }

    /**
     * Generate response based on question type.
     */
    private function generateResponseByType(QuestionResponse $questionResponse, Question $question): void
    {
        switch ($question->getType()) {
            case Question::TYPE_TEXT:
                $textResponses = [
                    'J\'ai trouvé cette formation très enrichissante et pratique.',
                    'Les outils présentés répondent parfaitement à mes besoins professionnels.',
                    'L\'approche pédagogique était claire et bien structurée.',
                    'Je recommande cette formation à mes collègues.',
                    'Les exemples concrets m\'ont aidé à mieux comprendre les concepts.',
                    'Le formateur était très compétent et disponible pour répondre aux questions.',
                    'Cette formation va me permettre d\'améliorer mon efficacité au travail.',
                    'J\'aurais aimé plus de temps pour les exercices pratiques.',
                ];
                $questionResponse->setTextResponse($this->faker->randomElement($textResponses));
                break;

            case Question::TYPE_TEXTAREA:
                $longResponses = [
                    'Cette formation m\'a apporté de nouvelles compétences techniques que je peux immédiatement appliquer dans mon travail. L\'alternance entre théorie et pratique était parfaitement équilibrée. Je recommande vivement cette approche pédagogique.',
                    'Le contenu était très riche et bien organisé. Les supports de cours sont de qualité et me serviront de référence. Le formateur était à l\'écoute et a su adapter son discours à notre niveau de connaissances.',
                    'Formation très complète qui a dépassé mes attentes. Les outils présentés vont considérablement améliorer mon efficacité quotidienne. J\'aurais apprécié quelques exercices supplémentaires pour consolider certains acquis.',
                    'Excellente formation avec un formateur expert dans son domaine. Les méthodes enseignées sont immédiatement applicables en entreprise. La documentation fournie est complète et bien structurée.',
                ];
                $questionResponse->setTextResponse($this->faker->randomElement($longResponses));
                break;

            case Question::TYPE_EMAIL:
                $questionResponse->setTextResponse($this->faker->email());
                break;

            case Question::TYPE_NUMBER:
                $questionResponse->setNumberResponse($this->faker->numberBetween(1, 10));
                break;

            case Question::TYPE_DATE:
                $questionResponse->setDateResponse($this->faker->dateTimeBetween('-1 year', '+1 year'));
                break;

            case Question::TYPE_SINGLE_CHOICE:
            case Question::TYPE_MULTIPLE_CHOICE:
                $this->generateChoiceResponse($questionResponse, $question);
                break;

            case Question::TYPE_FILE_UPLOAD:
                // Simulate uploaded files
                $fileNames = [
                    'document_evaluation.pdf',
                    'rapport_formation.docx',
                    'certificat_competences.jpg',
                    'attestation_presence.pdf',
                    'projet_final.zip',
                ];
                $questionResponse->setFileResponse($this->faker->randomElement($fileNames));
                break;
        }
    }

    /**
     * Generate choice response for single/multiple choice questions.
     */
    private function generateChoiceResponse(QuestionResponse $questionResponse, Question $question): void
    {
        $options = $question->getOptions();

        if ($options->isEmpty()) {
            return;
        }

        $optionIds = $options->map(static fn ($option) => $option->getId())->toArray();

        if ($question->getType() === Question::TYPE_SINGLE_CHOICE) {
            // Single choice: select one option
            $selectedOption = $this->faker->randomElement($optionIds);
            $questionResponse->setChoiceResponse([$selectedOption]);
        } else {
            // Multiple choice: select 1-3 options
            $numberOfSelections = $this->faker->numberBetween(1, min(3, count($optionIds)));
            $selectedOptions = $this->faker->randomElements($optionIds, $numberOfSelections);
            $questionResponse->setChoiceResponse($selectedOptions);
        }
    }
}
