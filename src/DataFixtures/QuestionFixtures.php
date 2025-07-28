<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Assessment\Question;
use App\Entity\Assessment\Questionnaire;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Question fixtures for EPROFOS platform.
 *
 * Creates realistic questions for questionnaires with various types:
 * text, textarea, single_choice, multiple_choice, file_upload, etc.
 */
class QuestionFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * Load question fixtures.
     */
    public function load(ObjectManager $manager): void
    {
        // PHP/Symfony positioning questionnaire questions
        $this->createPhpSymfonyQuestions($manager);

        // Leadership evaluation questionnaire questions
        $this->createLeadershipQuestions($manager);

        // Cybersecurity skills assessment questions
        $this->createCybersecurityQuestions($manager);

        // Excel assessment questions
        $this->createExcelQuestions($manager);

        // English positioning questions
        $this->createEnglishQuestions($manager);

        // Marketing satisfaction questions
        $this->createMarketingSatisfactionQuestions($manager);

        // General satisfaction questions
        $this->createGeneralSatisfactionQuestions($manager);

        $manager->flush();
    }

    /**
     * Define fixture dependencies.
     */
    public function getDependencies(): array
    {
        return [
            QuestionnaireFixtures::class,
        ];
    }

    /**
     * Create questions for PHP/Symfony positioning questionnaire.
     */
    private function createPhpSymfonyQuestions(ObjectManager $manager): void
    {
        /** @var Questionnaire $questionnaire */
        $questionnaire = $this->getReference(QuestionnaireFixtures::QUESTIONNAIRE_PHP_POSITIONING, Questionnaire::class);

        $questions = [
            [
                'questionText' => 'Quelle est votre expérience en développement PHP ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 1,
                'isRequired' => true,
                'helpText' => 'Sélectionnez l\'option qui correspond le mieux à votre niveau d\'expérience.',
                'points' => 5,
            ],
            [
                'questionText' => 'Avez-vous déjà utilisé un framework PHP ? Si oui, lesquels ?',
                'type' => Question::TYPE_MULTIPLE_CHOICE,
                'orderIndex' => 2,
                'isRequired' => true,
                'helpText' => 'Vous pouvez sélectionner plusieurs frameworks.',
                'points' => 10,
            ],
            [
                'questionText' => 'Décrivez brièvement votre expérience avec les bases de données relationnelles.',
                'type' => Question::TYPE_TEXTAREA,
                'orderIndex' => 3,
                'isRequired' => true,
                'helpText' => 'Mentionnez les SGBD utilisés et le type de projets.',
                'placeholder' => 'Exemple: J\'ai utilisé MySQL et PostgreSQL pour des projets e-commerce...',
                'maxLength' => 500,
                'points' => 8,
            ],
            [
                'questionText' => 'Connaissez-vous les concepts de la programmation orientée objet ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 4,
                'isRequired' => true,
                'points' => 7,
            ],
            [
                'questionText' => 'Avez-vous déjà travaillé avec des API REST ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 5,
                'isRequired' => true,
                'points' => 6,
            ],
            [
                'questionText' => 'Quel est votre niveau de confort avec Git et le versioning ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 6,
                'isRequired' => true,
                'points' => 4,
            ],
            [
                'questionText' => 'Avez-vous déjà utilisé Symfony ? Si oui, quelle version ?',
                'type' => Question::TYPE_TEXT,
                'orderIndex' => 7,
                'isRequired' => false,
                'helpText' => 'Indiquez la version et le type de projet.',
                'placeholder' => 'Exemple: Symfony 6.2 pour un site vitrine',
                'maxLength' => 200,
                'points' => 10,
            ],
            [
                'questionText' => 'Quels outils de développement utilisez-vous actuellement ?',
                'type' => Question::TYPE_MULTIPLE_CHOICE,
                'orderIndex' => 8,
                'isRequired' => false,
                'helpText' => 'Sélectionnez tous les outils que vous utilisez régulièrement.',
                'points' => 5,
            ],
            [
                'questionText' => 'Uploadez votre CV (optionnel)',
                'type' => Question::TYPE_FILE_UPLOAD,
                'orderIndex' => 9,
                'isRequired' => false,
                'helpText' => 'Votre CV nous aidera à mieux comprendre votre profil.',
                'allowedFileTypes' => ['pdf', 'doc', 'docx'],
                'maxFileSize' => 5242880, // 5MB
                'points' => 0,
            ],
            [
                'questionText' => 'Combien d\'années d\'expérience avez-vous en développement web ?',
                'type' => Question::TYPE_NUMBER,
                'orderIndex' => 10,
                'isRequired' => true,
                'helpText' => 'Indiquez le nombre d\'années (peut être 0 pour débutant).',
                'points' => 3,
            ],
        ];

        foreach ($questions as $index => $questionData) {
            $question = new Question();
            $question->setQuestionText($questionData['questionText']);
            $question->setType($questionData['type']);
            $question->setOrderIndex($questionData['orderIndex']);
            $question->setIsRequired($questionData['isRequired']);
            $question->setPoints($questionData['points']);
            $question->setQuestionnaire($questionnaire);

            if (isset($questionData['helpText'])) {
                $question->setHelpText($questionData['helpText']);
            }
            if (isset($questionData['placeholder'])) {
                $question->setPlaceholder($questionData['placeholder']);
            }
            if (isset($questionData['maxLength'])) {
                $question->setMaxLength($questionData['maxLength']);
            }
            if (isset($questionData['allowedFileTypes'])) {
                $question->setAllowedFileTypes($questionData['allowedFileTypes']);
            }
            if (isset($questionData['maxFileSize'])) {
                $question->setMaxFileSize($questionData['maxFileSize']);
            }

            $manager->persist($question);

            // Add reference for option creation
            $this->addReference('php_question_' . ($index + 1), $question);
        }
    }

    /**
     * Create questions for Leadership evaluation questionnaire.
     */
    private function createLeadershipQuestions(ObjectManager $manager): void
    {
        /** @var Questionnaire $questionnaire */
        $questionnaire = $this->getReference(QuestionnaireFixtures::QUESTIONNAIRE_LEADERSHIP_EVALUATION, Questionnaire::class);

        $questions = [
            [
                'questionText' => 'Comment évaluez-vous votre capacité à motiver votre équipe après cette formation ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 1,
                'isRequired' => true,
                'points' => 10,
            ],
            [
                'questionText' => 'Décrivez une situation récente où vous avez appliqué les techniques de management apprises en formation.',
                'type' => Question::TYPE_TEXTAREA,
                'orderIndex' => 2,
                'isRequired' => true,
                'helpText' => 'Soyez précis sur la situation, les actions menées et les résultats obtenus.',
                'maxLength' => 800,
                'points' => 15,
            ],
            [
                'questionText' => 'Quelles techniques de gestion des conflits maîtrisez-vous maintenant ?',
                'type' => Question::TYPE_MULTIPLE_CHOICE,
                'orderIndex' => 3,
                'isRequired' => true,
                'points' => 12,
            ],
            [
                'questionText' => 'Sur une échelle de 1 à 10, comment évaluez-vous votre progression en leadership ?',
                'type' => Question::TYPE_NUMBER,
                'orderIndex' => 4,
                'isRequired' => true,
                'helpText' => '1 = aucune progression, 10 = progression excellente',
                'points' => 8,
            ],
            [
                'questionText' => 'Avez-vous mis en place un plan de développement pour vos collaborateurs ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 5,
                'isRequired' => true,
                'points' => 10,
            ],
            [
                'questionText' => 'Quels sont vos principaux défis en tant que manager actuellement ?',
                'type' => Question::TYPE_TEXTAREA,
                'orderIndex' => 6,
                'isRequired' => false,
                'helpText' => 'Cette information nous aide à vous accompagner après la formation.',
                'maxLength' => 400,
                'points' => 5,
            ],
            [
                'questionText' => 'À quelle fréquence organisez-vous des entretiens individuels avec vos collaborateurs ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 7,
                'isRequired' => true,
                'points' => 8,
            ],
            [
                'questionText' => 'Quels outils de communication utilisez-vous avec votre équipe ?',
                'type' => Question::TYPE_MULTIPLE_CHOICE,
                'orderIndex' => 8,
                'isRequired' => false,
                'points' => 6,
            ],
        ];

        foreach ($questions as $index => $questionData) {
            $question = new Question();
            $question->setQuestionText($questionData['questionText']);
            $question->setType($questionData['type']);
            $question->setOrderIndex($questionData['orderIndex']);
            $question->setIsRequired($questionData['isRequired']);
            $question->setPoints($questionData['points']);
            $question->setQuestionnaire($questionnaire);

            if (isset($questionData['helpText'])) {
                $question->setHelpText($questionData['helpText']);
            }
            if (isset($questionData['maxLength'])) {
                $question->setMaxLength($questionData['maxLength']);
            }

            $manager->persist($question);
            $this->addReference('leadership_question_' . ($index + 1), $question);
        }
    }

    /**
     * Create questions for Cybersecurity skills assessment.
     */
    private function createCybersecurityQuestions(ObjectManager $manager): void
    {
        /** @var Questionnaire $questionnaire */
        $questionnaire = $this->getReference(QuestionnaireFixtures::QUESTIONNAIRE_CYBERSECURITY_SKILLS, Questionnaire::class);

        $questions = [
            [
                'questionText' => 'Qu\'est-ce qu\'une attaque par déni de service (DDoS) ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 1,
                'isRequired' => true,
                'points' => 10,
            ],
            [
                'questionText' => 'Quelles sont les principales vulnérabilités du top 10 OWASP ?',
                'type' => Question::TYPE_MULTIPLE_CHOICE,
                'orderIndex' => 2,
                'isRequired' => true,
                'helpText' => 'Sélectionnez toutes les vulnérabilités que vous connaissez.',
                'points' => 15,
            ],
            [
                'questionText' => 'Expliquez le principe du chiffrement asymétrique.',
                'type' => Question::TYPE_TEXTAREA,
                'orderIndex' => 3,
                'isRequired' => true,
                'helpText' => 'Décrivez le fonctionnement avec clés publiques et privées.',
                'maxLength' => 600,
                'points' => 12,
            ],
            [
                'questionText' => 'Qu\'est-ce que le RGPD et quelles sont ses principales exigences ?',
                'type' => Question::TYPE_TEXTAREA,
                'orderIndex' => 4,
                'isRequired' => true,
                'maxLength' => 500,
                'points' => 10,
            ],
            [
                'questionText' => 'Quel type d\'authentification est le plus sécurisé ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 5,
                'isRequired' => true,
                'points' => 8,
            ],
            [
                'questionText' => 'Avez-vous déjà mis en place une politique de sécurité ? Décrivez votre expérience.',
                'type' => Question::TYPE_TEXTAREA,
                'orderIndex' => 6,
                'isRequired' => false,
                'helpText' => 'Décrivez les mesures mises en place et les défis rencontrés.',
                'maxLength' => 400,
                'points' => 8,
            ],
            [
                'questionText' => 'Quels outils de sécurité connaissez-vous ?',
                'type' => Question::TYPE_MULTIPLE_CHOICE,
                'orderIndex' => 7,
                'isRequired' => true,
                'points' => 12,
            ],
            [
                'questionText' => 'Combien de temps faut-il en moyenne pour détecter une intrusion ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 8,
                'isRequired' => true,
                'points' => 6,
            ],
            [
                'questionText' => 'Que signifie CIA en cybersécurité ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 9,
                'isRequired' => true,
                'points' => 8,
            ],
            [
                'questionText' => 'Uploadez un exemple de politique de sécurité que vous avez rédigée (optionnel)',
                'type' => Question::TYPE_FILE_UPLOAD,
                'orderIndex' => 10,
                'isRequired' => false,
                'helpText' => 'Document PDF uniquement, données anonymisées.',
                'allowedFileTypes' => ['pdf'],
                'maxFileSize' => 2097152, // 2MB
                'points' => 5,
            ],
        ];

        foreach ($questions as $index => $questionData) {
            $question = new Question();
            $question->setQuestionText($questionData['questionText']);
            $question->setType($questionData['type']);
            $question->setOrderIndex($questionData['orderIndex']);
            $question->setIsRequired($questionData['isRequired']);
            $question->setPoints($questionData['points']);
            $question->setQuestionnaire($questionnaire);

            if (isset($questionData['helpText'])) {
                $question->setHelpText($questionData['helpText']);
            }
            if (isset($questionData['maxLength'])) {
                $question->setMaxLength($questionData['maxLength']);
            }
            if (isset($questionData['allowedFileTypes'])) {
                $question->setAllowedFileTypes($questionData['allowedFileTypes']);
            }
            if (isset($questionData['maxFileSize'])) {
                $question->setMaxFileSize($questionData['maxFileSize']);
            }

            $manager->persist($question);
            $this->addReference('cybersecurity_question_' . ($index + 1), $question);
        }
    }

    /**
     * Create questions for Excel assessment.
     */
    private function createExcelQuestions(ObjectManager $manager): void
    {
        /** @var Questionnaire $questionnaire */
        $questionnaire = $this->getReference(QuestionnaireFixtures::QUESTIONNAIRE_EXCEL_ASSESSMENT, Questionnaire::class);

        $questions = [
            [
                'questionText' => 'Quel est votre niveau actuel avec Excel ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 1,
                'isRequired' => true,
                'points' => 5,
            ],
            [
                'questionText' => 'Avez-vous déjà créé des tableaux croisés dynamiques ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 2,
                'isRequired' => true,
                'points' => 8,
            ],
            [
                'questionText' => 'Quelles fonctions Excel maîtrisez-vous ?',
                'type' => Question::TYPE_MULTIPLE_CHOICE,
                'orderIndex' => 3,
                'isRequired' => true,
                'helpText' => 'Sélectionnez toutes les fonctions que vous utilisez couramment.',
                'points' => 12,
            ],
            [
                'questionText' => 'Avez-vous déjà utilisé Power BI ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 4,
                'isRequired' => true,
                'points' => 10,
            ],
            [
                'questionText' => 'Décrivez le projet Excel le plus complexe que vous ayez réalisé.',
                'type' => Question::TYPE_TEXTAREA,
                'orderIndex' => 5,
                'isRequired' => false,
                'helpText' => 'Décrivez les fonctionnalités utilisées et l\'objectif du projet.',
                'maxLength' => 400,
                'points' => 8,
            ],
            [
                'questionText' => 'Connaissez-vous le langage VBA ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 6,
                'isRequired' => true,
                'points' => 10,
            ],
            [
                'questionText' => 'À quelle fréquence utilisez-vous Excel dans votre travail ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 7,
                'isRequired' => true,
                'points' => 4,
            ],
            [
                'questionText' => 'Quels types de graphiques savez-vous créer ?',
                'type' => Question::TYPE_MULTIPLE_CHOICE,
                'orderIndex' => 8,
                'isRequired' => true,
                'points' => 8,
            ],
            [
                'questionText' => 'Avez-vous déjà connecté Excel à des bases de données externes ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 9,
                'isRequired' => true,
                'points' => 12,
            ],
            [
                'questionText' => 'Quelle est votre expérience avec les formules de recherche (VLOOKUP, INDEX/MATCH) ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 10,
                'isRequired' => true,
                'points' => 10,
            ],
        ];

        foreach ($questions as $index => $questionData) {
            $question = new Question();
            $question->setQuestionText($questionData['questionText']);
            $question->setType($questionData['type']);
            $question->setOrderIndex($questionData['orderIndex']);
            $question->setIsRequired($questionData['isRequired']);
            $question->setPoints($questionData['points']);
            $question->setQuestionnaire($questionnaire);

            if (isset($questionData['helpText'])) {
                $question->setHelpText($questionData['helpText']);
            }
            if (isset($questionData['maxLength'])) {
                $question->setMaxLength($questionData['maxLength']);
            }

            $manager->persist($question);
            $this->addReference('excel_question_' . ($index + 1), $question);
        }
    }

    /**
     * Create questions for English positioning.
     */
    private function createEnglishQuestions(ObjectManager $manager): void
    {
        /** @var Questionnaire $questionnaire */
        $questionnaire = $this->getReference(QuestionnaireFixtures::QUESTIONNAIRE_ENGLISH_POSITIONING, Questionnaire::class);

        $questions = [
            [
                'questionText' => 'What is your current English level?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 1,
                'isRequired' => true,
                'points' => 5,
            ],
            [
                'questionText' => 'How often do you use English in your professional environment?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 2,
                'isRequired' => true,
                'points' => 6,
            ],
            [
                'questionText' => 'Which business English skills do you want to improve?',
                'type' => Question::TYPE_MULTIPLE_CHOICE,
                'orderIndex' => 3,
                'isRequired' => true,
                'helpText' => 'Select all areas you want to focus on.',
                'points' => 10,
            ],
            [
                'questionText' => 'Describe a typical business meeting you attend in English.',
                'type' => Question::TYPE_TEXTAREA,
                'orderIndex' => 4,
                'isRequired' => false,
                'helpText' => 'This helps us understand your current usage context.',
                'maxLength' => 300,
                'points' => 8,
            ],
            [
                'questionText' => 'Complete this sentence: "I would like to _____ my English skills because..."',
                'type' => Question::TYPE_TEXT,
                'orderIndex' => 5,
                'isRequired' => true,
                'helpText' => 'Write your answer in English.',
                'maxLength' => 200,
                'points' => 7,
            ],
            [
                'questionText' => 'Have you ever taken an English certification exam?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 6,
                'isRequired' => true,
                'points' => 5,
            ],
            [
                'questionText' => 'What is your TOEIC score (if applicable)?',
                'type' => Question::TYPE_NUMBER,
                'orderIndex' => 7,
                'isRequired' => false,
                'helpText' => 'Enter your most recent TOEIC score (0-990 points).',
                'points' => 8,
            ],
            [
                'questionText' => 'Which English-speaking countries have you visited or worked in?',
                'type' => Question::TYPE_MULTIPLE_CHOICE,
                'orderIndex' => 8,
                'isRequired' => false,
                'points' => 4,
            ],
            [
                'questionText' => 'Do you feel comfortable presenting in English?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 9,
                'isRequired' => true,
                'points' => 8,
            ],
            [
                'questionText' => 'What are your main difficulties when communicating in English?',
                'type' => Question::TYPE_MULTIPLE_CHOICE,
                'orderIndex' => 10,
                'isRequired' => true,
                'helpText' => 'This will help us personalize your training program.',
                'points' => 10,
            ],
        ];

        foreach ($questions as $index => $questionData) {
            $question = new Question();
            $question->setQuestionText($questionData['questionText']);
            $question->setType($questionData['type']);
            $question->setOrderIndex($questionData['orderIndex']);
            $question->setIsRequired($questionData['isRequired']);
            $question->setPoints($questionData['points']);
            $question->setQuestionnaire($questionnaire);

            if (isset($questionData['helpText'])) {
                $question->setHelpText($questionData['helpText']);
            }
            if (isset($questionData['maxLength'])) {
                $question->setMaxLength($questionData['maxLength']);
            }

            $manager->persist($question);
            $this->addReference('english_question_' . ($index + 1), $question);
        }
    }

    /**
     * Create questions for Marketing satisfaction.
     */
    private function createMarketingSatisfactionQuestions(ObjectManager $manager): void
    {
        /** @var Questionnaire $questionnaire */
        $questionnaire = $this->getReference(QuestionnaireFixtures::QUESTIONNAIRE_MARKETING_SATISFACTION, Questionnaire::class);

        $questions = [
            [
                'questionText' => 'Comment évaluez-vous globalement cette formation ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 1,
                'isRequired' => true,
                'points' => 0,
            ],
            [
                'questionText' => 'Le contenu de la formation a-t-il répondu à vos attentes ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 2,
                'isRequired' => true,
                'points' => 0,
            ],
            [
                'questionText' => 'Quels modules vous ont été les plus utiles ?',
                'type' => Question::TYPE_MULTIPLE_CHOICE,
                'orderIndex' => 3,
                'isRequired' => false,
                'points' => 0,
            ],
            [
                'questionText' => 'Recommanderiez-vous cette formation à un collègue ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 4,
                'isRequired' => true,
                'points' => 0,
            ],
            [
                'questionText' => 'Avez-vous des suggestions d\'amélioration pour cette formation ?',
                'type' => Question::TYPE_TEXTAREA,
                'orderIndex' => 5,
                'isRequired' => false,
                'helpText' => 'Vos suggestions nous aident à améliorer nos formations.',
                'maxLength' => 500,
                'points' => 0,
            ],
            [
                'questionText' => 'Comment évaluez-vous la qualité de l\'animation ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 6,
                'isRequired' => true,
                'points' => 0,
            ],
            [
                'questionText' => 'Les supports de formation étaient-ils de qualité ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 7,
                'isRequired' => true,
                'points' => 0,
            ],
            [
                'questionText' => 'Pensez-vous appliquer les connaissances acquises dans votre travail ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 8,
                'isRequired' => true,
                'points' => 0,
            ],
            [
                'questionText' => 'Quelle note donneriez-vous à l\'organisation logistique ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 9,
                'isRequired' => true,
                'points' => 0,
            ],
            [
                'questionText' => 'Souhaitez-vous être recontacté pour un suivi post-formation ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 10,
                'isRequired' => false,
                'points' => 0,
            ],
        ];

        foreach ($questions as $index => $questionData) {
            $question = new Question();
            $question->setQuestionText($questionData['questionText']);
            $question->setType($questionData['type']);
            $question->setOrderIndex($questionData['orderIndex']);
            $question->setIsRequired($questionData['isRequired']);
            $question->setPoints($questionData['points']);
            $question->setQuestionnaire($questionnaire);

            if (isset($questionData['helpText'])) {
                $question->setHelpText($questionData['helpText']);
            }
            if (isset($questionData['maxLength'])) {
                $question->setMaxLength($questionData['maxLength']);
            }

            $manager->persist($question);
            $this->addReference('marketing_satisfaction_question_' . ($index + 1), $question);
        }
    }

    /**
     * Create questions for General satisfaction.
     */
    private function createGeneralSatisfactionQuestions(ObjectManager $manager): void
    {
        /** @var Questionnaire $questionnaire */
        $questionnaire = $this->getReference(QuestionnaireFixtures::QUESTIONNAIRE_GENERAL_SATISFACTION, Questionnaire::class);

        $questions = [
            [
                'questionText' => 'Évaluation globale de la formation',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 1,
                'isRequired' => true,
                'helpText' => 'Note de 1 (très insatisfait) à 5 (très satisfait)',
                'points' => 0,
            ],
            [
                'questionText' => 'Les objectifs de formation ont-ils été atteints ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 2,
                'isRequired' => true,
                'points' => 0,
            ],
            [
                'questionText' => 'La durée de la formation était-elle appropriée ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 3,
                'isRequired' => true,
                'points' => 0,
            ],
            [
                'questionText' => 'Comment évaluez-vous la pédagogie du formateur ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 4,
                'isRequired' => true,
                'points' => 0,
            ],
            [
                'questionText' => 'Les méthodes pédagogiques utilisées étaient-elles adaptées ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 5,
                'isRequired' => true,
                'points' => 0,
            ],
            [
                'questionText' => 'Quel aspect de la formation avez-vous le plus apprécié ?',
                'type' => Question::TYPE_TEXTAREA,
                'orderIndex' => 6,
                'isRequired' => false,
                'maxLength' => 300,
                'points' => 0,
            ],
            [
                'questionText' => 'Que pourrait-on améliorer ?',
                'type' => Question::TYPE_TEXTAREA,
                'orderIndex' => 7,
                'isRequired' => false,
                'helpText' => 'Vos suggestions sont précieuses pour nous améliorer.',
                'maxLength' => 300,
                'points' => 0,
            ],
            [
                'questionText' => 'Recommanderiez-vous EPROFOS à votre entourage professionnel ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 8,
                'isRequired' => true,
                'points' => 0,
            ],
            [
                'questionText' => 'Dans quels délais pensez-vous pouvoir appliquer les acquis ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 9,
                'isRequired' => false,
                'points' => 0,
            ],
            [
                'questionText' => 'Souhaiteriez-vous suivre d\'autres formations chez EPROFOS ?',
                'type' => Question::TYPE_SINGLE_CHOICE,
                'orderIndex' => 10,
                'isRequired' => false,
                'points' => 0,
            ],
            [
                'questionText' => 'Commentaires libres',
                'type' => Question::TYPE_TEXTAREA,
                'orderIndex' => 11,
                'isRequired' => false,
                'helpText' => 'Partagez tout ce qui vous semble important.',
                'maxLength' => 1000,
                'points' => 0,
            ],
            [
                'questionText' => 'Votre date de naissance (pour statistiques anonymes)',
                'type' => Question::TYPE_DATE,
                'orderIndex' => 12,
                'isRequired' => false,
                'helpText' => 'Ces informations nous aident à adapter nos formations.',
                'points' => 0,
            ],
        ];

        foreach ($questions as $index => $questionData) {
            $question = new Question();
            $question->setQuestionText($questionData['questionText']);
            $question->setType($questionData['type']);
            $question->setOrderIndex($questionData['orderIndex']);
            $question->setIsRequired($questionData['isRequired']);
            $question->setPoints($questionData['points']);
            $question->setQuestionnaire($questionnaire);

            if (isset($questionData['helpText'])) {
                $question->setHelpText($questionData['helpText']);
            }
            if (isset($questionData['maxLength'])) {
                $question->setMaxLength($questionData['maxLength']);
            }

            $manager->persist($question);
            $this->addReference('general_satisfaction_question_' . ($index + 1), $question);
        }
    }
}
