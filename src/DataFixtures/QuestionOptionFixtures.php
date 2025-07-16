<?php

namespace App\DataFixtures;

use App\Entity\Question;
use App\Entity\QuestionOption;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * QuestionOption fixtures for EPROFOS platform
 * 
 * Creates realistic options for multiple choice and single choice questions
 */
class QuestionOptionFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * Load question option fixtures
     */
    public function load(ObjectManager $manager): void
    {
        // PHP/Symfony questionnaire options
        $this->createPhpSymfonyOptions($manager);
        
        // Leadership questionnaire options
        $this->createLeadershipOptions($manager);
        
        // Cybersecurity questionnaire options
        $this->createCybersecurityOptions($manager);
        
        // Excel questionnaire options
        $this->createExcelOptions($manager);
        
        // English questionnaire options
        $this->createEnglishOptions($manager);
        
        // Marketing satisfaction options
        $this->createMarketingSatisfactionQuestions($manager);
        
        // General satisfaction options
        $this->createGeneralSatisfactionOptions($manager);

        $manager->flush();
    }

    /**
     * Create options for PHP/Symfony questionnaire
     */
    private function createPhpSymfonyOptions(ObjectManager $manager): void
    {
        // Question 1: Experience in PHP development
        $question1 = $this->getReference('php_question_1', Question::class);
        $options1 = [
            ['text' => 'Débutant (moins de 1 an)', 'correct' => false, 'points' => 2],
            ['text' => 'Intermédiaire (1-3 ans)', 'correct' => false, 'points' => 5],
            ['text' => 'Avancé (3-5 ans)', 'correct' => false, 'points' => 8],
            ['text' => 'Expert (plus de 5 ans)', 'correct' => false, 'points' => 10],
            ['text' => 'Aucune expérience', 'correct' => false, 'points' => 0],
        ];
        $this->createOptionsForQuestion($manager, $question1, $options1);

        // Question 2: PHP frameworks used
        $question2 = $this->getReference('php_question_2', Question::class);
        $options2 = [
            ['text' => 'Symfony', 'correct' => true, 'points' => 10],
            ['text' => 'Laravel', 'correct' => false, 'points' => 8],
            ['text' => 'CodeIgniter', 'correct' => false, 'points' => 5],
            ['text' => 'Zend Framework/Laminas', 'correct' => false, 'points' => 7],
            ['text' => 'CakePHP', 'correct' => false, 'points' => 6],
            ['text' => 'Slim Framework', 'correct' => false, 'points' => 4],
            ['text' => 'Aucun framework', 'correct' => false, 'points' => 0],
        ];
        $this->createOptionsForQuestion($manager, $question2, $options2);

        // Question 4: OOP concepts knowledge
        $question4 = $this->getReference('php_question_4', Question::class);
        $options4 = [
            ['text' => 'Parfaitement maîtrisés', 'correct' => true, 'points' => 10],
            ['text' => 'Bien maîtrisés', 'correct' => false, 'points' => 7],
            ['text' => 'Notions de base', 'correct' => false, 'points' => 4],
            ['text' => 'Peu familier', 'correct' => false, 'points' => 2],
            ['text' => 'Pas du tout', 'correct' => false, 'points' => 0],
        ];
        $this->createOptionsForQuestion($manager, $question4, $options4);

        // Question 5: REST API experience
        $question5 = $this->getReference('php_question_5', Question::class);
        $options5 = [
            ['text' => 'Oui, j\'ai développé plusieurs API', 'correct' => true, 'points' => 10],
            ['text' => 'Oui, j\'ai développé quelques API', 'correct' => false, 'points' => 7],
            ['text' => 'J\'ai consommé des API mais pas développé', 'correct' => false, 'points' => 4],
            ['text' => 'Notions théoriques seulement', 'correct' => false, 'points' => 2],
            ['text' => 'Aucune expérience', 'correct' => false, 'points' => 0],
        ];
        $this->createOptionsForQuestion($manager, $question5, $options5);

        // Question 6: Git comfort level
        $question6 = $this->getReference('php_question_6', Question::class);
        $options6 = [
            ['text' => 'Expert (branches, merges, rebases)', 'correct' => false, 'points' => 10],
            ['text' => 'Avancé (branches, merges)', 'correct' => false, 'points' => 7],
            ['text' => 'Intermédiaire (add, commit, push, pull)', 'correct' => false, 'points' => 5],
            ['text' => 'Débutant (commandes de base)', 'correct' => false, 'points' => 3],
            ['text' => 'Jamais utilisé', 'correct' => false, 'points' => 0],
        ];
        $this->createOptionsForQuestion($manager, $question6, $options6);

        // Question 8: Development tools
        $question8 = $this->getReference('php_question_8', Question::class);
        $options8 = [
            ['text' => 'PhpStorm/IntelliJ IDEA', 'correct' => false, 'points' => 5],
            ['text' => 'Visual Studio Code', 'correct' => false, 'points' => 4],
            ['text' => 'Sublime Text', 'correct' => false, 'points' => 3],
            ['text' => 'Atom', 'correct' => false, 'points' => 3],
            ['text' => 'Vim/Neovim', 'correct' => false, 'points' => 4],
            ['text' => 'Composer', 'correct' => false, 'points' => 3],
            ['text' => 'Docker', 'correct' => false, 'points' => 5],
            ['text' => 'Xdebug', 'correct' => false, 'points' => 4],
        ];
        $this->createOptionsForQuestion($manager, $question8, $options8);
    }

    /**
     * Create options for Leadership questionnaire
     */
    private function createLeadershipOptions(ObjectManager $manager): void
    {
        // Question 1: Motivation capacity
        $question1 = $this->getReference('leadership_question_1', Question::class);
        $options1 = [
            ['text' => 'Excellente, j\'arrive à motiver facilement', 'correct' => true, 'points' => 10],
            ['text' => 'Bonne, avec quelques efforts', 'correct' => false, 'points' => 7],
            ['text' => 'Correcte, selon les situations', 'correct' => false, 'points' => 5],
            ['text' => 'Difficile, j\'ai besoin d\'amélioration', 'correct' => false, 'points' => 3],
            ['text' => 'Très difficile pour moi', 'correct' => false, 'points' => 1],
        ];
        $this->createOptionsForQuestion($manager, $question1, $options1);

        // Question 3: Conflict management techniques
        $question3 = $this->getReference('leadership_question_3', Question::class);
        $options3 = [
            ['text' => 'Médiation', 'correct' => true, 'points' => 5],
            ['text' => 'Écoute active', 'correct' => true, 'points' => 4],
            ['text' => 'Négociation', 'correct' => true, 'points' => 4],
            ['text' => 'Communication non-violente', 'correct' => true, 'points' => 5],
            ['text' => 'Résolution collaborative', 'correct' => true, 'points' => 4],
            ['text' => 'Gestion des émotions', 'correct' => true, 'points' => 3],
        ];
        $this->createOptionsForQuestion($manager, $question3, $options3);

        // Question 5: Development plan implementation
        $question5 = $this->getReference('leadership_question_5', Question::class);
        $options5 = [
            ['text' => 'Oui, pour tous mes collaborateurs', 'correct' => true, 'points' => 10],
            ['text' => 'Oui, pour la plupart', 'correct' => false, 'points' => 7],
            ['text' => 'Partiellement', 'correct' => false, 'points' => 5],
            ['text' => 'En cours de mise en place', 'correct' => false, 'points' => 3],
            ['text' => 'Pas encore', 'correct' => false, 'points' => 1],
        ];
        $this->createOptionsForQuestion($manager, $question5, $options5);

        // Question 7: Individual meeting frequency
        $question7 = $this->getReference('leadership_question_7', Question::class);
        $options7 = [
            ['text' => 'Chaque semaine', 'correct' => true, 'points' => 10],
            ['text' => 'Toutes les deux semaines', 'correct' => false, 'points' => 8],
            ['text' => 'Une fois par mois', 'correct' => false, 'points' => 6],
            ['text' => 'Tous les trimestres', 'correct' => false, 'points' => 3],
            ['text' => 'Rarement ou jamais', 'correct' => false, 'points' => 0],
        ];
        $this->createOptionsForQuestion($manager, $question7, $options7);

        // Question 8: Communication tools
        $question8 = $this->getReference('leadership_question_8', Question::class);
        $options8 = [
            ['text' => 'Réunions d\'équipe régulières', 'correct' => false, 'points' => 3],
            ['text' => 'Outils collaboratifs (Slack, Teams)', 'correct' => false, 'points' => 2],
            ['text' => 'Entretiens individuels', 'correct' => false, 'points' => 4],
            ['text' => 'Tableaux de bord partagés', 'correct' => false, 'points' => 2],
            ['text' => 'Feedback régulier informel', 'correct' => false, 'points' => 3],
            ['text' => 'Emails et communications écrites', 'correct' => false, 'points' => 1],
        ];
        $this->createOptionsForQuestion($manager, $question8, $options8);
    }

    /**
     * Create options for Cybersecurity questionnaire
     */
    private function createCybersecurityOptions(ObjectManager $manager): void
    {
        // Question 1: DDoS attack definition
        $question1 = $this->getReference('cybersecurity_question_1', Question::class);
        $options1 = [
            ['text' => 'Une attaque qui surcharge un serveur pour le rendre indisponible', 'correct' => true, 'points' => 10],
            ['text' => 'Une attaque qui vole des données personnelles', 'correct' => false, 'points' => 0],
            ['text' => 'Une attaque qui infecte un système avec un virus', 'correct' => false, 'points' => 0],
            ['text' => 'Une attaque qui prend le contrôle d\'un ordinateur', 'correct' => false, 'points' => 0],
        ];
        $this->createOptionsForQuestion($manager, $question1, $options1);

        // Question 2: OWASP Top 10 vulnerabilities
        $question2 = $this->getReference('cybersecurity_question_2', Question::class);
        $options2 = [
            ['text' => 'Injection SQL', 'correct' => true, 'points' => 3],
            ['text' => 'Cross-Site Scripting (XSS)', 'correct' => true, 'points' => 3],
            ['text' => 'Broken Authentication', 'correct' => true, 'points' => 3],
            ['text' => 'Sensitive Data Exposure', 'correct' => true, 'points' => 3],
            ['text' => 'XML External Entities (XXE)', 'correct' => true, 'points' => 2],
            ['text' => 'Broken Access Control', 'correct' => true, 'points' => 3],
            ['text' => 'Security Misconfiguration', 'correct' => true, 'points' => 2],
            ['text' => 'Insecure Deserialization', 'correct' => true, 'points' => 2],
        ];
        $this->createOptionsForQuestion($manager, $question2, $options2);

        // Question 5: Most secure authentication
        $question5 = $this->getReference('cybersecurity_question_5', Question::class);
        $options5 = [
            ['text' => 'Authentification multi-facteurs (MFA)', 'correct' => true, 'points' => 10],
            ['text' => 'Mot de passe complexe uniquement', 'correct' => false, 'points' => 3],
            ['text' => 'Authentification biométrique seule', 'correct' => false, 'points' => 5],
            ['text' => 'Authentification par SMS', 'correct' => false, 'points' => 2],
        ];
        $this->createOptionsForQuestion($manager, $question5, $options5);

        // Question 7: Security tools knowledge
        $question7 = $this->getReference('cybersecurity_question_7', Question::class);
        $options7 = [
            ['text' => 'Nmap', 'correct' => false, 'points' => 3],
            ['text' => 'Wireshark', 'correct' => false, 'points' => 4],
            ['text' => 'Metasploit', 'correct' => false, 'points' => 5],
            ['text' => 'Burp Suite', 'correct' => false, 'points' => 4],
            ['text' => 'OWASP ZAP', 'correct' => false, 'points' => 3],
            ['text' => 'Nessus', 'correct' => false, 'points' => 4],
            ['text' => 'Snort', 'correct' => false, 'points' => 3],
            ['text' => 'Antivirus standard', 'correct' => false, 'points' => 1],
        ];
        $this->createOptionsForQuestion($manager, $question7, $options7);

        // Question 8: Intrusion detection time
        $question8 = $this->getReference('cybersecurity_question_8', Question::class);
        $options8 = [
            ['text' => 'Quelques minutes', 'correct' => false, 'points' => 2],
            ['text' => 'Quelques heures', 'correct' => false, 'points' => 5],
            ['text' => 'Quelques jours', 'correct' => false, 'points' => 8],
            ['text' => 'Plusieurs semaines à mois', 'correct' => true, 'points' => 10],
        ];
        $this->createOptionsForQuestion($manager, $question8, $options8);

        // Question 9: CIA in cybersecurity
        $question9 = $this->getReference('cybersecurity_question_9', Question::class);
        $options9 = [
            ['text' => 'Confidentialité, Intégrité, Disponibilité', 'correct' => true, 'points' => 10],
            ['text' => 'Contrôle, Inspection, Authentification', 'correct' => false, 'points' => 0],
            ['text' => 'Cryptographie, Identification, Autorisation', 'correct' => false, 'points' => 0],
            ['text' => 'Certification, Investigation, Audit', 'correct' => false, 'points' => 0],
        ];
        $this->createOptionsForQuestion($manager, $question9, $options9);
    }

    /**
     * Create options for Excel questionnaire
     */
    private function createExcelOptions(ObjectManager $manager): void
    {
        // Question 1: Current Excel level
        $question1 = $this->getReference('excel_question_1', Question::class);
        $options1 = [
            ['text' => 'Débutant', 'correct' => false, 'points' => 1],
            ['text' => 'Intermédiaire', 'correct' => false, 'points' => 3],
            ['text' => 'Avancé', 'correct' => false, 'points' => 5],
            ['text' => 'Expert', 'correct' => false, 'points' => 5],
        ];
        $this->createOptionsForQuestion($manager, $question1, $options1);

        // Question 2: Pivot tables experience
        $question2 = $this->getReference('excel_question_2', Question::class);
        $options2 = [
            ['text' => 'Oui, régulièrement et de manière avancée', 'correct' => true, 'points' => 10],
            ['text' => 'Oui, occasionnellement', 'correct' => false, 'points' => 6],
            ['text' => 'Oui, mais très basique', 'correct' => false, 'points' => 3],
            ['text' => 'Non, jamais', 'correct' => false, 'points' => 0],
        ];
        $this->createOptionsForQuestion($manager, $question2, $options2);

        // Question 3: Excel functions mastered
        $question3 = $this->getReference('excel_question_3', Question::class);
        $options3 = [
            ['text' => 'VLOOKUP/RECHERCHEV', 'correct' => false, 'points' => 3],
            ['text' => 'INDEX/MATCH', 'correct' => false, 'points' => 4],
            ['text' => 'SUMIF/SUMIFS', 'correct' => false, 'points' => 2],
            ['text' => 'COUNTIF/COUNTIFS', 'correct' => false, 'points' => 2],
            ['text' => 'IF/SI complexes', 'correct' => false, 'points' => 2],
            ['text' => 'Array formulas', 'correct' => false, 'points' => 5],
            ['text' => 'Power Query', 'correct' => false, 'points' => 5],
        ];
        $this->createOptionsForQuestion($manager, $question3, $options3);

        // Question 4: Power BI experience
        $question4 = $this->getReference('excel_question_4', Question::class);
        $options4 = [
            ['text' => 'Oui, je l\'utilise régulièrement', 'correct' => false, 'points' => 10],
            ['text' => 'Oui, quelques fois', 'correct' => false, 'points' => 6],
            ['text' => 'J\'ai testé mais pas utilisé en production', 'correct' => false, 'points' => 3],
            ['text' => 'Non, jamais utilisé', 'correct' => false, 'points' => 0],
        ];
        $this->createOptionsForQuestion($manager, $question4, $options4);

        // Question 6: VBA knowledge
        $question6 = $this->getReference('excel_question_6', Question::class);
        $options6 = [
            ['text' => 'Oui, je développe des macros complexes', 'correct' => false, 'points' => 10],
            ['text' => 'Oui, des macros simples', 'correct' => false, 'points' => 6],
            ['text' => 'J\'ai des notions de base', 'correct' => false, 'points' => 3],
            ['text' => 'Non, pas du tout', 'correct' => false, 'points' => 0],
        ];
        $this->createOptionsForQuestion($manager, $question6, $options6);

        // Question 7: Excel usage frequency
        $question7 = $this->getReference('excel_question_7', Question::class);
        $options7 = [
            ['text' => 'Quotidiennement', 'correct' => false, 'points' => 4],
            ['text' => 'Plusieurs fois par semaine', 'correct' => false, 'points' => 3],
            ['text' => 'Une fois par semaine', 'correct' => false, 'points' => 2],
            ['text' => 'Occasionnellement', 'correct' => false, 'points' => 1],
            ['text' => 'Rarement', 'correct' => false, 'points' => 0],
        ];
        $this->createOptionsForQuestion($manager, $question7, $options7);

        // Question 8: Chart types creation
        $question8 = $this->getReference('excel_question_8', Question::class);
        $options8 = [
            ['text' => 'Graphiques en colonnes/barres', 'correct' => false, 'points' => 1],
            ['text' => 'Graphiques en secteurs/camembert', 'correct' => false, 'points' => 1],
            ['text' => 'Graphiques linéaires', 'correct' => false, 'points' => 1],
            ['text' => 'Graphiques en aires', 'correct' => false, 'points' => 2],
            ['text' => 'Graphiques scatter/nuages de points', 'correct' => false, 'points' => 2],
            ['text' => 'Graphiques combinés', 'correct' => false, 'points' => 3],
            ['text' => 'Graphiques waterfall', 'correct' => false, 'points' => 4],
        ];
        $this->createOptionsForQuestion($manager, $question8, $options8);

        // Question 9: External database connection
        $question9 = $this->getReference('excel_question_9', Question::class);
        $options9 = [
            ['text' => 'Oui, régulièrement', 'correct' => false, 'points' => 12],
            ['text' => 'Oui, occasionnellement', 'correct' => false, 'points' => 8],
            ['text' => 'Une ou deux fois', 'correct' => false, 'points' => 4],
            ['text' => 'Non, jamais', 'correct' => false, 'points' => 0],
        ];
        $this->createOptionsForQuestion($manager, $question9, $options9);

        // Question 10: Lookup formulas experience
        $question10 = $this->getReference('excel_question_10', Question::class);
        $options10 = [
            ['text' => 'Expert (XLOOKUP, INDEX/MATCH avancé)', 'correct' => false, 'points' => 10],
            ['text' => 'Avancé (INDEX/MATCH, VLOOKUP complexe)', 'correct' => false, 'points' => 7],
            ['text' => 'Intermédiaire (VLOOKUP standard)', 'correct' => false, 'points' => 4],
            ['text' => 'Débutant (VLOOKUP simple)', 'correct' => false, 'points' => 2],
            ['text' => 'Aucune expérience', 'correct' => false, 'points' => 0],
        ];
        $this->createOptionsForQuestion($manager, $question10, $options10);
    }

    /**
     * Create options for English questionnaire
     */
    private function createEnglishOptions(ObjectManager $manager): void
    {
        // Question 1: Current English level
        $question1 = $this->getReference('english_question_1', Question::class);
        $options1 = [
            ['text' => 'Beginner (A1-A2)', 'correct' => false, 'points' => 1],
            ['text' => 'Intermediate (B1-B2)', 'correct' => false, 'points' => 3],
            ['text' => 'Advanced (C1)', 'correct' => false, 'points' => 4],
            ['text' => 'Proficient (C2)', 'correct' => false, 'points' => 5],
            ['text' => 'Native speaker', 'correct' => false, 'points' => 5],
        ];
        $this->createOptionsForQuestion($manager, $question1, $options1);

        // Question 2: Professional English usage frequency
        $question2 = $this->getReference('english_question_2', Question::class);
        $options2 = [
            ['text' => 'Daily', 'correct' => false, 'points' => 6],
            ['text' => 'Several times a week', 'correct' => false, 'points' => 4],
            ['text' => 'Once a week', 'correct' => false, 'points' => 3],
            ['text' => 'Monthly', 'correct' => false, 'points' => 2],
            ['text' => 'Rarely or never', 'correct' => false, 'points' => 0],
        ];
        $this->createOptionsForQuestion($manager, $question2, $options2);

        // Question 3: Business English skills to improve
        $question3 = $this->getReference('english_question_3', Question::class);
        $options3 = [
            ['text' => 'Business presentations', 'correct' => false, 'points' => 3],
            ['text' => 'Meeting participation', 'correct' => false, 'points' => 3],
            ['text' => 'Email writing', 'correct' => false, 'points' => 2],
            ['text' => 'Phone calls/video conferences', 'correct' => false, 'points' => 3],
            ['text' => 'Negotiations', 'correct' => false, 'points' => 4],
            ['text' => 'Report writing', 'correct' => false, 'points' => 2],
            ['text' => 'Small talk/networking', 'correct' => false, 'points' => 2],
        ];
        $this->createOptionsForQuestion($manager, $question3, $options3);

        // Question 6: English certification exam
        $question6 = $this->getReference('english_question_6', Question::class);
        $options6 = [
            ['text' => 'Yes, TOEIC', 'correct' => false, 'points' => 5],
            ['text' => 'Yes, TOEFL', 'correct' => false, 'points' => 5],
            ['text' => 'Yes, Cambridge (FCE, CAE, CPE)', 'correct' => false, 'points' => 5],
            ['text' => 'Yes, IELTS', 'correct' => false, 'points' => 5],
            ['text' => 'Yes, other certification', 'correct' => false, 'points' => 3],
            ['text' => 'No, never', 'correct' => false, 'points' => 0],
        ];
        $this->createOptionsForQuestion($manager, $question6, $options6);

        // Question 8: English-speaking countries visited
        $question8 = $this->getReference('english_question_8', Question::class);
        $options8 = [
            ['text' => 'United States', 'correct' => false, 'points' => 2],
            ['text' => 'United Kingdom', 'correct' => false, 'points' => 2],
            ['text' => 'Canada', 'correct' => false, 'points' => 2],
            ['text' => 'Australia', 'correct' => false, 'points' => 2],
            ['text' => 'New Zealand', 'correct' => false, 'points' => 2],
            ['text' => 'Ireland', 'correct' => false, 'points' => 2],
            ['text' => 'South Africa', 'correct' => false, 'points' => 2],
            ['text' => 'None', 'correct' => false, 'points' => 0],
        ];
        $this->createOptionsForQuestion($manager, $question8, $options8);

        // Question 9: Comfort with English presentations
        $question9 = $this->getReference('english_question_9', Question::class);
        $options9 = [
            ['text' => 'Very comfortable', 'correct' => false, 'points' => 8],
            ['text' => 'Somewhat comfortable', 'correct' => false, 'points' => 6],
            ['text' => 'Neutral', 'correct' => false, 'points' => 4],
            ['text' => 'Somewhat uncomfortable', 'correct' => false, 'points' => 2],
            ['text' => 'Very uncomfortable', 'correct' => false, 'points' => 0],
        ];
        $this->createOptionsForQuestion($manager, $question9, $options9);

        // Question 10: Main English communication difficulties
        $question10 = $this->getReference('english_question_10', Question::class);
        $options10 = [
            ['text' => 'Pronunciation', 'correct' => false, 'points' => 3],
            ['text' => 'Grammar', 'correct' => false, 'points' => 2],
            ['text' => 'Vocabulary', 'correct' => false, 'points' => 2],
            ['text' => 'Listening comprehension', 'correct' => false, 'points' => 3],
            ['text' => 'Speaking fluency', 'correct' => false, 'points' => 3],
            ['text' => 'Writing skills', 'correct' => false, 'points' => 2],
            ['text' => 'Confidence', 'correct' => false, 'points' => 3],
            ['text' => 'None, I\'m comfortable', 'correct' => false, 'points' => 0],
        ];
        $this->createOptionsForQuestion($manager, $question10, $options10);
    }

    /**
     * Create options for Marketing satisfaction questionnaire
     */
    private function createMarketingSatisfactionQuestions(ObjectManager $manager): void
    {
        // Standard satisfaction scale options used for multiple questions
        $satisfactionScale = [
            ['text' => 'Très satisfait', 'correct' => false, 'points' => 0],
            ['text' => 'Satisfait', 'correct' => false, 'points' => 0],
            ['text' => 'Neutre', 'correct' => false, 'points' => 0],
            ['text' => 'Insatisfait', 'correct' => false, 'points' => 0],
            ['text' => 'Très insatisfait', 'correct' => false, 'points' => 0],
        ];

        $yesNoScale = [
            ['text' => 'Tout à fait', 'correct' => false, 'points' => 0],
            ['text' => 'Plutôt oui', 'correct' => false, 'points' => 0],
            ['text' => 'Partiellement', 'correct' => false, 'points' => 0],
            ['text' => 'Plutôt non', 'correct' => false, 'points' => 0],
            ['text' => 'Pas du tout', 'correct' => false, 'points' => 0],
        ];

        // Question 1: Overall evaluation
        $question1 = $this->getReference('marketing_satisfaction_question_1', Question::class);
        $this->createOptionsForQuestion($manager, $question1, $satisfactionScale);

        // Question 2: Expectations met
        $question2 = $this->getReference('marketing_satisfaction_question_2', Question::class);
        $this->createOptionsForQuestion($manager, $question2, $yesNoScale);

        // Question 3: Most useful modules
        $question3 = $this->getReference('marketing_satisfaction_question_3', Question::class);
        $options3 = [
            ['text' => 'Stratégie digitale', 'correct' => false, 'points' => 0],
            ['text' => 'SEO et SEA', 'correct' => false, 'points' => 0],
            ['text' => 'Réseaux sociaux', 'correct' => false, 'points' => 0],
            ['text' => 'Analytics et ROI', 'correct' => false, 'points' => 0],
            ['text' => 'Création de contenu', 'correct' => false, 'points' => 0],
            ['text' => 'Email marketing', 'correct' => false, 'points' => 0],
        ];
        $this->createOptionsForQuestion($manager, $question3, $options3);

        // Question 4: Recommendation
        $question4 = $this->getReference('marketing_satisfaction_question_4', Question::class);
        $recommendationOptions = [
            ['text' => 'Certainement', 'correct' => false, 'points' => 0],
            ['text' => 'Probablement', 'correct' => false, 'points' => 0],
            ['text' => 'Peut-être', 'correct' => false, 'points' => 0],
            ['text' => 'Probablement pas', 'correct' => false, 'points' => 0],
            ['text' => 'Certainement pas', 'correct' => false, 'points' => 0],
        ];
        $this->createOptionsForQuestion($manager, $question4, $recommendationOptions);

        // Questions 6, 7, 8, 9 use satisfaction scales
        for ($i = 6; $i <= 9; $i++) {
            $question = $this->getReference('marketing_satisfaction_question_' . $i, Question::class);
            $this->createOptionsForQuestion($manager, $question, $satisfactionScale);
        }

        // Question 10: Follow-up contact
        $question10 = $this->getReference('marketing_satisfaction_question_10', Question::class);
        $contactOptions = [
            ['text' => 'Oui, dans 3 mois', 'correct' => false, 'points' => 0],
            ['text' => 'Oui, dans 6 mois', 'correct' => false, 'points' => 0],
            ['text' => 'Oui, dans 1 an', 'correct' => false, 'points' => 0],
            ['text' => 'Non merci', 'correct' => false, 'points' => 0],
        ];
        $this->createOptionsForQuestion($manager, $question10, $contactOptions);
    }

    /**
     * Create options for General satisfaction questionnaire
     */
    private function createGeneralSatisfactionOptions(ObjectManager $manager): void
    {
        // Standard satisfaction scale
        $satisfactionScale = [
            ['text' => 'Très satisfait (5)', 'correct' => false, 'points' => 0],
            ['text' => 'Satisfait (4)', 'correct' => false, 'points' => 0],
            ['text' => 'Neutre (3)', 'correct' => false, 'points' => 0],
            ['text' => 'Insatisfait (2)', 'correct' => false, 'points' => 0],
            ['text' => 'Très insatisfait (1)', 'correct' => false, 'points' => 0],
        ];

        $yesNoScale = [
            ['text' => 'Complètement', 'correct' => false, 'points' => 0],
            ['text' => 'En grande partie', 'correct' => false, 'points' => 0],
            ['text' => 'Partiellement', 'correct' => false, 'points' => 0],
            ['text' => 'Peu', 'correct' => false, 'points' => 0],
            ['text' => 'Pas du tout', 'correct' => false, 'points' => 0],
        ];

        // Question 1: Overall evaluation
        $question1 = $this->getReference('general_satisfaction_question_1', Question::class);
        $this->createOptionsForQuestion($manager, $question1, $satisfactionScale);

        // Question 2: Objectives achieved
        $question2 = $this->getReference('general_satisfaction_question_2', Question::class);
        $this->createOptionsForQuestion($manager, $question2, $yesNoScale);

        // Question 3: Duration appropriateness
        $question3 = $this->getReference('general_satisfaction_question_3', Question::class);
        $durationOptions = [
            ['text' => 'Parfaitement adaptée', 'correct' => false, 'points' => 0],
            ['text' => 'Un peu trop courte', 'correct' => false, 'points' => 0],
            ['text' => 'Un peu trop longue', 'correct' => false, 'points' => 0],
            ['text' => 'Beaucoup trop courte', 'correct' => false, 'points' => 0],
            ['text' => 'Beaucoup trop longue', 'correct' => false, 'points' => 0],
        ];
        $this->createOptionsForQuestion($manager, $question3, $durationOptions);

        // Questions 4 and 5: Trainer and methods evaluation
        for ($i = 4; $i <= 5; $i++) {
            $question = $this->getReference('general_satisfaction_question_' . $i, Question::class);
            $this->createOptionsForQuestion($manager, $question, $satisfactionScale);
        }

        // Question 8: EPROFOS recommendation
        $question8 = $this->getReference('general_satisfaction_question_8', Question::class);
        $recommendationOptions = [
            ['text' => 'Certainement', 'correct' => false, 'points' => 0],
            ['text' => 'Probablement', 'correct' => false, 'points' => 0],
            ['text' => 'Peut-être', 'correct' => false, 'points' => 0],
            ['text' => 'Probablement pas', 'correct' => false, 'points' => 0],
            ['text' => 'Certainement pas', 'correct' => false, 'points' => 0],
        ];
        $this->createOptionsForQuestion($manager, $question8, $recommendationOptions);

        // Question 9: Application timeline
        $question9 = $this->getReference('general_satisfaction_question_9', Question::class);
        $timelineOptions = [
            ['text' => 'Immédiatement', 'correct' => false, 'points' => 0],
            ['text' => 'Dans le mois', 'correct' => false, 'points' => 0],
            ['text' => 'Dans les 3 mois', 'correct' => false, 'points' => 0],
            ['text' => 'Dans les 6 mois', 'correct' => false, 'points' => 0],
            ['text' => 'Plus tard', 'correct' => false, 'points' => 0],
        ];
        $this->createOptionsForQuestion($manager, $question9, $timelineOptions);

        // Question 10: Future training interest
        $question10 = $this->getReference('general_satisfaction_question_10', Question::class);
        $futureTrainingOptions = [
            ['text' => 'Oui, très intéressé', 'correct' => false, 'points' => 0],
            ['text' => 'Oui, intéressé', 'correct' => false, 'points' => 0],
            ['text' => 'Peut-être', 'correct' => false, 'points' => 0],
            ['text' => 'Probablement pas', 'correct' => false, 'points' => 0],
            ['text' => 'Non', 'correct' => false, 'points' => 0],
        ];
        $this->createOptionsForQuestion($manager, $question10, $futureTrainingOptions);
    }

    /**
     * Helper method to create options for a question
     */
    private function createOptionsForQuestion(ObjectManager $manager, Question $question, array $optionsData): void
    {
        foreach ($optionsData as $index => $optionData) {
            $option = new QuestionOption();
            $option->setOptionText($optionData['text']);
            $option->setOrderIndex($index + 1);
            $option->setIsCorrect($optionData['correct']);
            $option->setPoints($optionData['points']);
            $option->setQuestion($question);

            $manager->persist($option);
        }
    }

    /**
     * Define fixture dependencies
     */
    public function getDependencies(): array
    {
        return [
            QuestionFixtures::class,
        ];
    }
}
