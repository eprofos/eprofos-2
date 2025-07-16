<?php

namespace App\DataFixtures;

use App\Entity\Formation;
use App\Entity\Question;
use App\Entity\Questionnaire;
use App\Entity\QuestionnaireResponse;
use App\Entity\QuestionResponse;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;

/**
 * QuestionnaireResponse fixtures for EPROFOS platform
 * 
 * Creates realistic questionnaire responses with various completion states
 * to test the questionnaire system functionality
 */
class QuestionnaireResponseFixtures extends Fixture implements DependentFixtureInterface
{
    private Generator $faker;

    public function __construct()
    {
        $this->faker = Factory::create('fr_FR');
    }

    /**
     * Load questionnaire response fixtures
     */
    public function load(ObjectManager $manager): void
    {
        // Create responses for each questionnaire
        $this->createPhpSymfonyResponses($manager);
        $this->createLeadershipResponses($manager);
        $this->createCybersecurityResponses($manager);
        $this->createExcelResponses($manager);
        $this->createEnglishResponses($manager);
        $this->createMarketingSatisfactionResponses($manager);
        $this->createGeneralSatisfactionResponses($manager);

        $manager->flush();
    }

    /**
     * Create responses for PHP/Symfony positioning questionnaire
     */
    private function createPhpSymfonyResponses(ObjectManager $manager): void
    {
        /** @var Questionnaire $questionnaire */
        $questionnaire = $this->getReference(QuestionnaireFixtures::QUESTIONNAIRE_PHP_POSITIONING, Questionnaire::class);
        $formation = $questionnaire->getFormation();

        // Create 15 responses with different completion states
        for ($i = 1; $i <= 15; $i++) {
            $response = new QuestionnaireResponse();
            $response->setFirstName($this->faker->firstName);
            $response->setLastName($this->faker->lastName);
            $response->setEmail($this->faker->unique()->email);
            $response->setPhone($this->faker->optional(0.7)->phoneNumber);
            $response->setCompany($this->faker->optional(0.8)->company);
            $response->setQuestionnaire($questionnaire);
            $response->setFormation($formation);

            // Different completion states
            if ($i <= 10) {
                // Completed responses
                $response->setStatus(QuestionnaireResponse::STATUS_COMPLETED);
                $response->markAsCompleted();
                $response->setCompletedAt(new \DateTimeImmutable('-' . $this->faker->numberBetween(1, 30) . ' days'));
                $response->setDurationMinutes($this->faker->numberBetween(15, 35));
                
                $this->createPhpSymfonyQuestionResponses($manager, $response, $questionnaire);
                $response->calculateScore();
                
                // Some responses are evaluated
                if ($i <= 7) {
                    $response->markAsEvaluated();
                    $response->setEvaluatedAt(new \DateTimeImmutable('-' . $this->faker->numberBetween(1, 25) . ' days'));
                    $response->setEvaluatorNotes($this->generateEvaluatorNotes('PHP'));
                    $scorePercentage = $response->getScorePercentage() ?? '0';
                    $response->setRecommendation($this->generateRecommendation($scorePercentage));
                }
            } elseif ($i <= 13) {
                // In progress responses
                $response->setStatus(QuestionnaireResponse::STATUS_IN_PROGRESS);
                $response->markAsInProgress();
                $response->setCurrentStep($this->faker->numberBetween(1, 3));
                $response->setStartedAt(new \DateTimeImmutable('-' . $this->faker->numberBetween(1, 7) . ' days'));
                
                $this->createPartialPhpSymfonyQuestionResponses($manager, $response, $questionnaire, $response->getCurrentStep());
            } else {
                // Started but abandoned responses
                $response->setStatus(QuestionnaireResponse::STATUS_ABANDONED);
                $response->setStartedAt(new \DateTimeImmutable('-' . $this->faker->numberBetween(5, 60) . ' days'));
                $response->setCurrentStep(1);
            }

            $manager->persist($response);
        }
    }

    /**
     * Create complete question responses for PHP/Symfony questionnaire
     */
    private function createPhpSymfonyQuestionResponses(ObjectManager $manager, QuestionnaireResponse $response, Questionnaire $questionnaire): void
    {
        $questions = $questionnaire->getQuestions();
        
        foreach ($questions as $question) {
            $questionResponse = new QuestionResponse();
            $questionResponse->setQuestion($question);
            $questionResponse->setQuestionnaireResponse($response);
            
            switch ($question->getType()) {
                case 'single_choice':
                    $this->setRandomSingleChoice($questionResponse, $question);
                    break;
                case 'multiple_choice':
                    $this->setRandomMultipleChoice($questionResponse, $question);
                    break;
                case 'text':
                    $this->setPhpTextResponse($questionResponse, $question);
                    break;
                case 'textarea':
                    $this->setPhpTextareaResponse($questionResponse, $question);
                    break;
                case 'number':
                    $this->setRandomNumberResponse($questionResponse, $question);
                    break;
                case 'file_upload':
                    if ($this->faker->boolean(30)) { // 30% chance of file upload
                        $questionResponse->setFileResponse('cv_' . $this->faker->uuid . '.pdf');
                    }
                    break;
            }
            
            $questionResponse->calculateScore();
            $manager->persist($questionResponse);
        }
    }

    /**
     * Create partial question responses for in-progress questionnaires
     */
    private function createPartialPhpSymfonyQuestionResponses(ObjectManager $manager, QuestionnaireResponse $response, Questionnaire $questionnaire, int $currentStep): void
    {
        $questions = $questionnaire->getQuestions();
        $questionsPerStep = $questionnaire->getQuestionsPerStep();
        $maxQuestionIndex = $currentStep * $questionsPerStep;
        
        $questionIndex = 0;
        foreach ($questions as $question) {
            if ($questionIndex >= $maxQuestionIndex) break;
            
            $questionResponse = new QuestionResponse();
            $questionResponse->setQuestion($question);
            $questionResponse->setQuestionnaireResponse($response);
            
            // Similar response logic but only for completed steps
            switch ($question->getType()) {
                case 'single_choice':
                    $this->setRandomSingleChoice($questionResponse, $question);
                    break;
                case 'multiple_choice':
                    $this->setRandomMultipleChoice($questionResponse, $question);
                    break;
                case 'text':
                    $this->setPhpTextResponse($questionResponse, $question);
                    break;
                case 'textarea':
                    $this->setPhpTextareaResponse($questionResponse, $question);
                    break;
                case 'number':
                    $this->setRandomNumberResponse($questionResponse, $question);
                    break;
            }
            
            $questionResponse->calculateScore();
            $manager->persist($questionResponse);
            $questionIndex++;
        }
    }

    /**
     * Create responses for Leadership evaluation questionnaire
     */
    private function createLeadershipResponses(ObjectManager $manager): void
    {
        /** @var Questionnaire $questionnaire */
        $questionnaire = $this->getReference(QuestionnaireFixtures::QUESTIONNAIRE_LEADERSHIP_EVALUATION, Questionnaire::class);
        $formation = $questionnaire->getFormation();

        // Create 12 responses
        for ($i = 1; $i <= 12; $i++) {
            $response = new QuestionnaireResponse();
            $response->setFirstName($this->faker->firstName);
            $response->setLastName($this->faker->lastName);
            $response->setEmail($this->faker->unique()->email);
            $response->setPhone($this->faker->optional(0.8)->phoneNumber);
            $response->setCompany($this->faker->company);
            $response->setQuestionnaire($questionnaire);
            $response->setFormation($formation);

            if ($i <= 9) {
                // Completed responses
                $response->setStatus(QuestionnaireResponse::STATUS_COMPLETED);
                $response->markAsCompleted();
                $response->setCompletedAt(new \DateTimeImmutable('-' . $this->faker->numberBetween(1, 20) . ' days'));
                $response->setDurationMinutes($this->faker->numberBetween(30, 50));
                
                $this->createLeadershipQuestionResponses($manager, $response, $questionnaire);
                $response->calculateScore();
                
                if ($i <= 6) {
                    $response->markAsEvaluated();
                    $response->setEvaluatedAt(new \DateTimeImmutable('-' . $this->faker->numberBetween(1, 15) . ' days'));
                    $response->setEvaluatorNotes($this->generateEvaluatorNotes('Leadership'));
                    $scorePercentage = $response->getScorePercentage() ?? '0';
                    $response->setRecommendation($this->generateRecommendation($scorePercentage));
                }
            } else {
                // In progress responses
                $response->setStatus(QuestionnaireResponse::STATUS_IN_PROGRESS);
                $response->markAsInProgress();
                $response->setCurrentStep($this->faker->numberBetween(1, 2));
                $response->setStartedAt(new \DateTimeImmutable('-' . $this->faker->numberBetween(1, 5) . ' days'));
            }

            $manager->persist($response);
        }
    }

    /**
     * Create question responses for Leadership questionnaire
     */
    private function createLeadershipQuestionResponses(ObjectManager $manager, QuestionnaireResponse $response, Questionnaire $questionnaire): void
    {
        $questions = $questionnaire->getQuestions();
        
        foreach ($questions as $question) {
            $questionResponse = new QuestionResponse();
            $questionResponse->setQuestion($question);
            $questionResponse->setQuestionnaireResponse($response);
            
            switch ($question->getType()) {
                case 'single_choice':
                    $this->setRandomSingleChoice($questionResponse, $question);
                    break;
                case 'multiple_choice':
                    $this->setRandomMultipleChoice($questionResponse, $question);
                    break;
                case 'textarea':
                    $this->setLeadershipTextareaResponse($questionResponse, $question);
                    break;
                case 'number':
                    $this->setRandomNumberResponse($questionResponse, $question);
                    break;
            }
            
            $questionResponse->calculateScore();
            $manager->persist($questionResponse);
        }
    }

    /**
     * Create responses for other questionnaires (simplified)
     */
    private function createCybersecurityResponses(ObjectManager $manager): void
    {
        $this->createGenericResponses($manager, QuestionnaireFixtures::QUESTIONNAIRE_CYBERSECURITY_SKILLS, 8);
    }

    private function createExcelResponses(ObjectManager $manager): void
    {
        $this->createGenericResponses($manager, QuestionnaireFixtures::QUESTIONNAIRE_EXCEL_ASSESSMENT, 12);
    }

    private function createEnglishResponses(ObjectManager $manager): void
    {
        $this->createGenericResponses($manager, QuestionnaireFixtures::QUESTIONNAIRE_ENGLISH_POSITIONING, 10);
    }

    private function createMarketingSatisfactionResponses(ObjectManager $manager): void
    {
        $this->createGenericResponses($manager, QuestionnaireFixtures::QUESTIONNAIRE_MARKETING_SATISFACTION, 20);
    }

    private function createGeneralSatisfactionResponses(ObjectManager $manager): void
    {
        $this->createGenericResponses($manager, QuestionnaireFixtures::QUESTIONNAIRE_GENERAL_SATISFACTION, 25);
    }

    /**
     * Generic method to create responses for any questionnaire
     */
    private function createGenericResponses(ObjectManager $manager, string $questionnaireReference, int $count): void
    {
        /** @var Questionnaire $questionnaire */
        $questionnaire = $this->getReference($questionnaireReference, Questionnaire::class);
        $formation = $questionnaire->getFormation();

        for ($i = 1; $i <= $count; $i++) {
            $response = new QuestionnaireResponse();
            $response->setFirstName($this->faker->firstName);
            $response->setLastName($this->faker->lastName);
            $response->setEmail($this->faker->unique()->email);
            $response->setPhone($this->faker->optional(0.6)->phoneNumber);
            $response->setCompany($this->faker->optional(0.7)->company);
            $response->setQuestionnaire($questionnaire);
            $response->setFormation($formation);

            // Most responses are completed
            if ($i <= ($count * 0.8)) {
                $response->setStatus(QuestionnaireResponse::STATUS_COMPLETED);
                $response->markAsCompleted();
                $response->setCompletedAt(new \DateTimeImmutable('-' . $this->faker->numberBetween(1, 90) . ' days'));
                $response->setDurationMinutes($this->faker->numberBetween(10, 60));
                
                $this->createGenericQuestionResponses($manager, $response, $questionnaire);
                $response->calculateScore();
            } elseif ($i <= ($count * 0.95)) {
                // Some in progress
                $response->setStatus(QuestionnaireResponse::STATUS_IN_PROGRESS);
                $response->markAsInProgress();
                $response->setCurrentStep($this->faker->numberBetween(1, $questionnaire->getStepCount()));
                $response->setStartedAt(new \DateTimeImmutable('-' . $this->faker->numberBetween(1, 10) . ' days'));
            } else {
                // Few abandoned
                $response->setStatus(QuestionnaireResponse::STATUS_ABANDONED);
                $response->setStartedAt(new \DateTimeImmutable('-' . $this->faker->numberBetween(10, 180) . ' days'));
            }

            $manager->persist($response);
        }
    }

    /**
     * Create generic question responses
     */
    private function createGenericQuestionResponses(ObjectManager $manager, QuestionnaireResponse $response, Questionnaire $questionnaire): void
    {
        $questions = $questionnaire->getQuestions();
        
        foreach ($questions as $question) {
            // Skip some optional questions randomly
            if (!$question->isRequired() && $this->faker->boolean(30)) {
                continue;
            }
            
            $questionResponse = new QuestionResponse();
            $questionResponse->setQuestion($question);
            $questionResponse->setQuestionnaireResponse($response);
            
            switch ($question->getType()) {
                case 'single_choice':
                    $this->setRandomSingleChoice($questionResponse, $question);
                    break;
                case 'multiple_choice':
                    $this->setRandomMultipleChoice($questionResponse, $question);
                    break;
                case 'text':
                    $this->setGenericTextResponse($questionResponse, $question);
                    break;
                case 'textarea':
                    $this->setGenericTextareaResponse($questionResponse, $question);
                    break;
                case 'number':
                    $this->setRandomNumberResponse($questionResponse, $question);
                    break;
                case 'date':
                    $this->setRandomDateResponse($questionResponse);
                    break;
                case 'email':
                    $questionResponse->setTextResponse($this->faker->email);
                    break;
                case 'file_upload':
                    if ($this->faker->boolean(20)) {
                        $questionResponse->setFileResponse('file_' . $this->faker->uuid . '.pdf');
                    }
                    break;
            }
            
            $questionResponse->calculateScore();
            $manager->persist($questionResponse);
        }
    }

    /**
     * Helper methods for setting specific response types
     */
    private function setRandomSingleChoice(QuestionResponse $questionResponse, Question $question): void
    {
        $options = $question->getActiveOptions()->toArray();
        if (!empty($options)) {
            $selectedOption = $this->faker->randomElement($options);
            $questionResponse->setChoiceResponse([$selectedOption->getId()]);
        }
    }

    private function setRandomMultipleChoice(QuestionResponse $questionResponse, Question $question): void
    {
        $options = $question->getActiveOptions()->toArray();
        if (!empty($options)) {
            $selectedCount = $this->faker->numberBetween(1, min(3, count($options)));
            $selectedOptions = $this->faker->randomElements($options, $selectedCount);
            $selectedIds = array_map(fn($option) => $option->getId(), $selectedOptions);
            $questionResponse->setChoiceResponse($selectedIds);
        }
    }

    private function setRandomNumberResponse(QuestionResponse $questionResponse, Question $question): void
    {
        // Context-specific number generation
        $questionText = strtolower($question->getQuestionText());
        
        if (strpos($questionText, 'année') !== false || strpos($questionText, 'expérience') !== false) {
            $questionResponse->setNumberResponse($this->faker->numberBetween(0, 15));
        } elseif (strpos($questionText, 'échelle') !== false || strpos($questionText, '1 à 10') !== false) {
            $questionResponse->setNumberResponse($this->faker->numberBetween(1, 10));
        } elseif (strpos($questionText, 'toeic') !== false) {
            $questionResponse->setNumberResponse($this->faker->numberBetween(300, 990));
        } else {
            $questionResponse->setNumberResponse($this->faker->numberBetween(1, 100));
        }
    }

    private function setRandomDateResponse(QuestionResponse $questionResponse): void
    {
        $questionResponse->setDateResponse($this->faker->dateTimeBetween('-70 years', '-18 years'));
    }

    private function setPhpTextResponse(QuestionResponse $questionResponse, Question $question): void
    {
        $questionText = strtolower($question->getQuestionText());
        
        if (strpos($questionText, 'symfony') !== false) {
            $responses = [
                'Symfony 6.2 pour un projet e-commerce',
                'Symfony 5.4 pour une application interne',
                'Symfony 7.0 en cours d\'apprentissage',
                'Quelques projets avec Symfony 4',
                'Non, mais j\'ai de l\'expérience avec Laravel'
            ];
            $questionResponse->setTextResponse($this->faker->randomElement($responses));
        } else {
            $questionResponse->setTextResponse($this->faker->sentence($this->faker->numberBetween(3, 15)));
        }
    }

    private function setPhpTextareaResponse(QuestionResponse $questionResponse, Question $question): void
    {
        $responses = [
            'J\'ai travaillé avec MySQL et PostgreSQL sur plusieurs projets e-commerce. J\'ai notamment développé une plateforme de vente en ligne avec gestion des stocks et paiements sécurisés.',
            'Mon expérience inclut la conception de bases de données pour des applications métier, avec optimisation des requêtes et mise en place de procédures stockées.',
            'J\'ai utilisé principalement MySQL dans des environnements LAMP, avec quelques projets utilisant SQLite pour le développement.',
            'Expérience limitée mais j\'ai suivi des formations et réalisé quelques projets personnels avec PostgreSQL.',
        ];
        $questionResponse->setTextResponse($this->faker->randomElement($responses));
    }

    private function setLeadershipTextareaResponse(QuestionResponse $questionResponse, Question $question): void
    {
        $questionText = strtolower($question->getQuestionText());
        
        if (strpos($questionText, 'situation') !== false) {
            $responses = [
                'Récemment, j\'ai appliqué la technique de l\'écoute active lors d\'un conflit entre deux membres de mon équipe. J\'ai organisé une réunion de médiation qui a permis de résoudre le malentendu et d\'améliorer la collaboration.',
                'J\'ai mis en place des entretiens individuels hebdomadaires avec mes collaborateurs pour mieux comprendre leurs besoins et les accompagner dans leur développement.',
                'Lors d\'un projet urgent, j\'ai utilisé les techniques de délégation apprises pour responsabiliser mon équipe tout en maintenant un suivi régulier.',
            ];
        } else {
            $responses = [
                'Mes principaux défis concernent la gestion du changement et l\'accompagnement des équipes dans la transformation digitale de l\'entreprise.',
                'Je souhaite améliorer ma capacité à donner du feedback constructif et à développer l\'autonomie de mes collaborateurs.',
                'La motivation d\'équipes multigénérationnelles reste un défi constant que je dois relever.',
            ];
        }
        
        $questionResponse->setTextResponse($this->faker->randomElement($responses));
    }

    private function setGenericTextResponse(QuestionResponse $questionResponse, Question $question): void
    {
        $maxLength = $question->getMaxLength() ?? 200;
        $wordCount = min(intval($maxLength / 10), 30);
        $questionResponse->setTextResponse($this->faker->sentence($this->faker->numberBetween(3, $wordCount)));
    }

    private function setGenericTextareaResponse(QuestionResponse $questionResponse, Question $question): void
    {
        $maxLength = $question->getMaxLength() ?? 500;
        $sentenceCount = min(intval($maxLength / 50), 8);
        $questionResponse->setTextResponse($this->faker->paragraph($this->faker->numberBetween(1, $sentenceCount)));
    }

    /**
     * Generate evaluator notes based on domain
     */
    private function generateEvaluatorNotes(string $domain): string
    {
        $notes = [
            'PHP' => [
                'Bon niveau technique global. Maîtrise correcte des concepts OOP et des frameworks.',
                'Expérience solide mais nécessite renforcement sur les aspects sécurité et optimisation.',
                'Profil junior avec de bonnes bases. Recommandé pour module débutant/intermédiaire.',
                'Niveau avancé confirmé. Peut intégrer directement les modules experts.',
            ],
            'Leadership' => [
                'Excellente progression observée. Application concrète des techniques managériales.',
                'Bon potentiel mais nécessite encore de la pratique pour la gestion des conflits.',
                'Manager expérimenté qui a su adapter les outils à son contexte professionnel.',
                'Bonnes capacités d\'écoute et de communication. À poursuivre avec modules avancés.',
            ],
        ];
        
        $domainNotes = $notes[$domain] ?? ['Évaluation en cours d\'analyse.'];
        return $this->faker->randomElement($domainNotes);
    }

    /**
     * Generate recommendation based on score
     */
    private function generateRecommendation(string $scorePercentage): string
    {
        $score = floatval($scorePercentage);
        
        if ($score >= 80) {
            return 'Excellent niveau. Recommandé pour modules avancés ou rôle de mentor.';
        } elseif ($score >= 60) {
            return 'Bon niveau. Formation adaptée, suivi personnalisé recommandé.';
        } elseif ($score >= 40) {
            return 'Niveau correct. Renforcement sur points identifiés nécessaire.';
        } else {
            return 'Niveau à consolider. Formation de base recommandée avant progression.';
        }
    }

    /**
     * Define fixture dependencies
     */
    public function getDependencies(): array
    {
        return [
            QuestionOptionFixtures::class,
        ];
    }
}
