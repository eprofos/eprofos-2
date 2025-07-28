<?php

namespace App\DataFixtures;

use App\Entity\Training\Formation;
use App\Entity\Assessment\Questionnaire;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Questionnaire fixtures for EPROFOS platform
 * 
 * Creates realistic questionnaires for positioning and evaluation
 * in compliance with Qualiopi criteria 2.8
 */
class QuestionnaireFixtures extends Fixture implements DependentFixtureInterface
{
    // Constants for referencing questionnaires in other fixtures
    public const QUESTIONNAIRE_PHP_POSITIONING = 'questionnaire_php_positioning';
    public const QUESTIONNAIRE_LEADERSHIP_EVALUATION = 'questionnaire_leadership_evaluation';
    public const QUESTIONNAIRE_CYBERSECURITY_SKILLS = 'questionnaire_cybersecurity_skills';
    public const QUESTIONNAIRE_EXCEL_ASSESSMENT = 'questionnaire_excel_assessment';
    public const QUESTIONNAIRE_ENGLISH_POSITIONING = 'questionnaire_english_positioning';
    public const QUESTIONNAIRE_MARKETING_SATISFACTION = 'questionnaire_marketing_satisfaction';
    public const QUESTIONNAIRE_GENERAL_SATISFACTION = 'questionnaire_general_satisfaction';

    private SluggerInterface $slugger;

    public function __construct(SluggerInterface $slugger)
    {
        $this->slugger = $slugger;
    }

    /**
     * Load questionnaire fixtures
     */
    public function load(ObjectManager $manager): void
    {
        // Get formation references
        $formations = $this->getFormationReferences($manager);

        $questionnaires = [
            [
                'title' => 'Questionnaire de positionnement - Développement PHP/Symfony',
                'description' => 'Évaluez vos compétences en développement PHP et framework Symfony pour personnaliser votre parcours de formation.',
                'type' => 'positioning',
                'status' => Questionnaire::STATUS_ACTIVE,
                'formation' => $formations['php'] ?? null,
                'isMultiStep' => true,
                'questionsPerStep' => 5,
                'allowBackNavigation' => true,
                'showProgressBar' => true,
                'requireAllQuestions' => true,
                'timeLimitMinutes' => 30,
                'welcomeMessage' => 'Bienvenue dans le questionnaire de positionnement pour la formation Développement PHP/Symfony. Ce questionnaire nous permettra d\'adapter la formation à votre niveau et vos besoins spécifiques.',
                'completionMessage' => 'Merci d\'avoir complété le questionnaire ! Nous analyserons vos réponses pour personnaliser votre parcours de formation. Vous recevrez un rapport détaillé sous 48h.',
                'emailSubject' => 'Résultats de votre questionnaire de positionnement PHP/Symfony',
                'emailTemplate' => 'Bonjour {firstName},\n\nMerci d\'avoir complété le questionnaire de positionnement. Votre score : {score}%.\n\nNous vous recontacterons prochainement pour personnaliser votre formation.\n\nCordialement,\nL\'équipe EPROFOS',
                'reference' => self::QUESTIONNAIRE_PHP_POSITIONING,
            ],
            [
                'title' => 'Évaluation des acquis - Leadership et Management',
                'description' => 'Questionnaire d\'évaluation pour mesurer l\'acquisition des compétences managériales et de leadership.',
                'type' => 'evaluation',
                'status' => Questionnaire::STATUS_ACTIVE,
                'formation' => $formations['leadership'] ?? null,
                'isMultiStep' => true,
                'questionsPerStep' => 4,
                'allowBackNavigation' => false,
                'showProgressBar' => true,
                'requireAllQuestions' => true,
                'timeLimitMinutes' => 45,
                'welcomeMessage' => 'Cette évaluation permettra de mesurer votre progression et l\'acquisition des compétences en leadership et management d\'équipe.',
                'completionMessage' => 'Évaluation terminée ! Vos réponses seront analysées par notre équipe pédagogique. Un feedback détaillé vous sera transmis dans les 72h.',
                'emailSubject' => 'Résultats de votre évaluation Leadership et Management',
                'emailTemplate' => 'Bonjour {firstName},\n\nVotre évaluation est terminée. Score obtenu : {score}%.\n\nUn entretien de débriefing sera planifié prochainement.\n\nCordialement,\nVotre formateur EPROFOS',
                'reference' => self::QUESTIONNAIRE_LEADERSHIP_EVALUATION,
            ],
            [
                'title' => 'Évaluation des compétences - Cybersécurité',
                'description' => 'Questionnaire technique pour évaluer vos connaissances en cybersécurité et protection des données.',
                'type' => 'skills_assessment',
                'status' => Questionnaire::STATUS_ACTIVE,
                'formation' => $formations['cybersecurity'] ?? null,
                'isMultiStep' => true,
                'questionsPerStep' => 6,
                'allowBackNavigation' => true,
                'showProgressBar' => true,
                'requireAllQuestions' => true,
                'timeLimitMinutes' => 60,
                'welcomeMessage' => 'Évaluation technique en cybersécurité. Ce questionnaire permet d\'identifier vos points forts et axes d\'amélioration dans le domaine de la sécurité informatique.',
                'completionMessage' => 'Évaluation technique terminée. Nos experts analyseront vos réponses pour établir un diagnostic personnalisé de vos compétences en cybersécurité.',
                'emailSubject' => 'Diagnostic de vos compétences en cybersécurité',
                'emailTemplate' => 'Bonjour {firstName},\n\nVotre évaluation technique est complète. Résultat : {score}%.\n\nUn rapport détaillé avec recommandations sera disponible sous 48h.\n\nCordialement,\nL\'équipe cybersécurité EPROFOS',
                'reference' => self::QUESTIONNAIRE_CYBERSECURITY_SKILLS,
            ],
            [
                'title' => 'Test de niveau - Excel Avancé et Power BI',
                'description' => 'Évaluez votre maîtrise d\'Excel et Power BI pour déterminer le niveau de formation approprié.',
                'type' => 'positioning',
                'status' => Questionnaire::STATUS_ACTIVE,
                'formation' => $formations['excel'] ?? null,
                'isMultiStep' => true,
                'questionsPerStep' => 5,
                'allowBackNavigation' => true,
                'showProgressBar' => true,
                'requireAllQuestions' => false,
                'timeLimitMinutes' => 25,
                'welcomeMessage' => 'Test de positionnement Excel et Power BI. Vos réponses nous aideront à adapter le contenu de la formation à votre niveau actuel.',
                'completionMessage' => 'Test terminé ! Votre niveau a été évalué. Nous ajusterons le programme de formation en conséquence.',
                'emailSubject' => 'Résultats de votre test de niveau Excel/Power BI',
                'emailTemplate' => 'Bonjour {firstName},\n\nVotre test de niveau est terminé. Niveau évalué : {scorePercentage}%.\n\nLe programme sera adapté à vos compétences.\n\nCordialement,\nL\'équipe formation EPROFOS',
                'reference' => self::QUESTIONNAIRE_EXCEL_ASSESSMENT,
            ],
            [
                'title' => 'Positionnement - Anglais Professionnel',
                'description' => 'Questionnaire de positionnement en anglais professionnel pour adapter votre formation à votre niveau actuel.',
                'type' => 'positioning',
                'status' => Questionnaire::STATUS_ACTIVE,
                'formation' => $formations['english'] ?? null,
                'isMultiStep' => true,
                'questionsPerStep' => 6,
                'allowBackNavigation' => true,
                'showProgressBar' => true,
                'requireAllQuestions' => true,
                'timeLimitMinutes' => 35,
                'welcomeMessage' => 'Welcome to the English proficiency assessment. This questionnaire will help us determine your current level and customize your learning path.',
                'completionMessage' => 'Assessment completed! Your English level has been evaluated. We will provide you with a personalized training program.',
                'emailSubject' => 'Your English proficiency assessment results',
                'emailTemplate' => 'Hello {firstName},\n\nYour English assessment is complete. Level: {scorePercentage}%.\n\nYour personalized program will be sent shortly.\n\nBest regards,\nEPROFOS English team',
                'reference' => self::QUESTIONNAIRE_ENGLISH_POSITIONING,
            ],
            [
                'title' => 'Satisfaction - Marketing Digital',
                'description' => 'Questionnaire de satisfaction pour la formation Marketing Digital et Réseaux Sociaux.',
                'type' => 'satisfaction',
                'status' => Questionnaire::STATUS_ACTIVE,
                'formation' => $formations['marketing'] ?? null,
                'isMultiStep' => false,
                'questionsPerStep' => 15,
                'allowBackNavigation' => true,
                'showProgressBar' => false,
                'requireAllQuestions' => false,
                'timeLimitMinutes' => null,
                'welcomeMessage' => 'Votre avis compte ! Ce questionnaire de satisfaction nous aide à améliorer continuellement nos formations en marketing digital.',
                'completionMessage' => 'Merci pour vos retours précieux ! Vos commentaires nous aident à améliorer la qualité de nos formations.',
                'emailSubject' => 'Merci pour votre évaluation de la formation Marketing Digital',
                'emailTemplate' => 'Bonjour {firstName},\n\nMerci d\'avoir pris le temps d\'évaluer notre formation.\n\nVos commentaires sont précieux pour nous améliorer.\n\nCordialement,\nL\'équipe EPROFOS',
                'reference' => self::QUESTIONNAIRE_MARKETING_SATISFACTION,
            ],
            [
                'title' => 'Questionnaire de satisfaction générale',
                'description' => 'Questionnaire de satisfaction standard applicable à toutes les formations EPROFOS.',
                'type' => 'satisfaction',
                'status' => Questionnaire::STATUS_ACTIVE,
                'formation' => null, // Generic questionnaire
                'isMultiStep' => false,
                'questionsPerStep' => 12,
                'allowBackNavigation' => true,
                'showProgressBar' => false,
                'requireAllQuestions' => false,
                'timeLimitMinutes' => null,
                'welcomeMessage' => 'Votre satisfaction est notre priorité. Ce questionnaire nous aide à maintenir la qualité de nos formations.',
                'completionMessage' => 'Merci d\'avoir partagé votre expérience ! Votre avis contribue à l\'amélioration continue de nos services.',
                'emailSubject' => 'Merci pour votre évaluation EPROFOS',
                'emailTemplate' => 'Bonjour {firstName},\n\nMerci pour votre évaluation de notre formation.\n\nNous restons à votre disposition pour tout complément.\n\nCordialement,\nL\'équipe EPROFOS',
                'reference' => self::QUESTIONNAIRE_GENERAL_SATISFACTION,
            ],
            [
                'title' => 'Questionnaire de positionnement - Gestion de Projet Agile',
                'description' => 'Évaluez votre niveau en gestion de projet agile et méthodologie Scrum avant la formation.',
                'type' => 'positioning',
                'status' => Questionnaire::STATUS_DRAFT,
                'formation' => $formations['scrum'] ?? null,
                'isMultiStep' => true,
                'questionsPerStep' => 4,
                'allowBackNavigation' => true,
                'showProgressBar' => true,
                'requireAllQuestions' => true,
                'timeLimitMinutes' => 20,
                'welcomeMessage' => 'Questionnaire de positionnement en agilité et Scrum. Vos réponses nous permettront d\'adapter la formation à votre expérience.',
                'completionMessage' => 'Positionnement terminé ! Nous analyserons vos réponses pour optimiser votre parcours de formation agile.',
                'emailSubject' => 'Résultats de votre positionnement Gestion de Projet Agile',
                'emailTemplate' => 'Bonjour {firstName},\n\nVotre positionnement agile est terminé. Niveau évalué : {scorePercentage}%.\n\nLa formation sera adaptée en conséquence.\n\nCordialement,\nVotre Scrum Master EPROFOS',
                'reference' => 'questionnaire_scrum_positioning',
            ],
        ];

        foreach ($questionnaires as $questionnaireData) {
            $questionnaire = new Questionnaire();
            $questionnaire->setTitle($questionnaireData['title']);
            $questionnaire->generateSlug($this->slugger);
            $questionnaire->setDescription($questionnaireData['description']);
            $questionnaire->setType($questionnaireData['type']);
            $questionnaire->setStatus($questionnaireData['status']);
            $questionnaire->setIsMultiStep($questionnaireData['isMultiStep']);
            $questionnaire->setQuestionsPerStep($questionnaireData['questionsPerStep']);
            $questionnaire->setAllowBackNavigation($questionnaireData['allowBackNavigation']);
            $questionnaire->setShowProgressBar($questionnaireData['showProgressBar']);
            $questionnaire->setRequireAllQuestions($questionnaireData['requireAllQuestions']);
            $questionnaire->setTimeLimitMinutes($questionnaireData['timeLimitMinutes']);
            $questionnaire->setWelcomeMessage($questionnaireData['welcomeMessage']);
            $questionnaire->setCompletionMessage($questionnaireData['completionMessage']);
            $questionnaire->setEmailSubject($questionnaireData['emailSubject']);
            $questionnaire->setEmailTemplate($questionnaireData['emailTemplate']);

            // Set formation if specified
            if ($questionnaireData['formation']) {
                $questionnaire->setFormation($questionnaireData['formation']);
            }

            $manager->persist($questionnaire);
            
            // Add reference for use in other fixtures
            $this->addReference($questionnaireData['reference'], $questionnaire);
        }

        $manager->flush();
    }

    /**
     * Get formation references by finding formations by title
     */
    private function getFormationReferences(ObjectManager $manager): array
    {
        $formationRepository = $manager->getRepository(Formation::class);
        
        // Map formation titles to their entities
        $formations = [];
        
        $formations['php'] = $formationRepository->findOneBy(['title' => 'Développement Web avec PHP et Symfony']);
        $formations['leadership'] = $formationRepository->findOneBy(['title' => 'Leadership et Management d\'Équipe']);
        $formations['cybersecurity'] = $formationRepository->findOneBy(['title' => 'Cybersécurité et Protection des Données']);
        $formations['excel'] = $formationRepository->findOneBy(['title' => 'Maîtrise d\'Excel Avancé et Power BI']);
        $formations['english'] = $formationRepository->findOneBy(['title' => 'Anglais Professionnel - Business English']);
        $formations['marketing'] = $formationRepository->findOneBy(['title' => 'Marketing Digital et Réseaux Sociaux']);
        $formations['scrum'] = $formationRepository->findOneBy(['title' => 'Gestion de Projet Agile - Scrum Master']);
        
        return $formations;
    }

    /**
     * Define fixture dependencies
     */
    public function getDependencies(): array
    {
        return [
            FormationFixtures::class,
        ];
    }
}
