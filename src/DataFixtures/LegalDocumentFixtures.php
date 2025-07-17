<?php

namespace App\DataFixtures;

use App\Entity\LegalDocument;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

/**
 * Fixtures for LegalDocument entity
 * 
 * Creates sample legal documents required by Qualiopi criterion 3.9
 */
class LegalDocumentFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // Create legal documents for each type
        $documents = [
            [
                'type' => LegalDocument::TYPE_INTERNAL_REGULATION,
                'title' => 'Règlement intérieur des stagiaires EPROFOS',
                'version' => '2.1',
                'content' => $this->getInternalRegulationContent(),
                'published' => true,
            ],
            [
                'type' => LegalDocument::TYPE_STUDENT_HANDBOOK,
                'title' => 'Livret d\'accueil stagiaire 2025',
                'version' => '1.3',
                'content' => $this->getStudentHandbookContent(),
                'published' => true,
            ],
            [
                'type' => LegalDocument::TYPE_TRAINING_TERMS,
                'title' => 'Conditions générales de formation',
                'version' => '3.0',
                'content' => $this->getTrainingTermsContent(),
                'published' => true,
            ],
            [
                'type' => LegalDocument::TYPE_ACCESSIBILITY_POLICY,
                'title' => 'Politique d\'accessibilité handicap',
                'version' => '1.0',
                'content' => $this->getAccessibilityPolicyContent(),
                'published' => true,
            ],
            [
                'type' => LegalDocument::TYPE_ACCESSIBILITY_PROCEDURES,
                'title' => 'Procédures d\'adaptation pédagogique',
                'version' => '1.1',
                'content' => $this->getAccessibilityProceduresContent(),
                'published' => true,
            ],
            [
                'type' => LegalDocument::TYPE_ACCESSIBILITY_FAQ,
                'title' => 'FAQ Accessibilité et handicap',
                'version' => '1.0',
                'content' => $this->getAccessibilityFaqContent(),
                'published' => true,
            ],
            // Draft documents
            [
                'type' => LegalDocument::TYPE_INTERNAL_REGULATION,
                'title' => 'Règlement intérieur - Version 2025',
                'version' => '2.2-draft',
                'content' => $this->getInternalRegulationContent() . "\n\n<!-- Version en cours de révision -->",
                'published' => false,
            ],
        ];

        foreach ($documents as $docData) {
            $document = new LegalDocument();
            $document->setType($docData['type'])
                     ->setTitle($docData['title'])
                     ->setVersion($docData['version'])
                     ->setContent($docData['content'])
                     ->setIsActive(true);

            if ($docData['published']) {
                $document->publish();
            }

            // Set metadata
            $metadata = [
                'created_by' => 'system',
                'approval_status' => $docData['published'] ? 'approved' : 'pending',
                'compliance_level' => 'qualiopi',
            ];
            $document->setMetadata($metadata);

            $manager->persist($document);
        }

        $manager->flush();

        echo "Created " . count($documents) . " legal documents.\n";
    }

    private function getInternalRegulationContent(): string
    {
        return <<<HTML
<h1>Règlement intérieur des stagiaires</h1>

<h2>Article 1 - Objet et champ d'application</h2>
<p>Le présent règlement s'applique à tous les stagiaires inscrits aux formations dispensées par EPROFOS, conformément aux exigences du référentiel national qualité Qualiopi.</p>

<h2>Article 2 - Droits et devoirs des stagiaires</h2>
<h3>2.1 Droits</h3>
<ul>
    <li>Droit à la formation professionnelle continue</li>
    <li>Droit à l'information sur les formations et leurs modalités</li>
    <li>Droit à l'évaluation et à la validation des acquis</li>
    <li>Droit à l'accompagnement individualisé</li>
    <li>Droit au respect de la confidentialité</li>
</ul>

<h3>2.2 Devoirs</h3>
<ul>
    <li>Respecter les horaires et le programme de formation</li>
    <li>Respecter les autres stagiaires et l'équipe pédagogique</li>
    <li>Prendre soin du matériel mis à disposition</li>
    <li>Suivre avec assiduité les enseignements</li>
    <li>Effectuer les travaux demandés dans les délais</li>
</ul>

<h2>Article 3 - Organisation des formations</h2>
<p>Les formations se déroulent selon les modalités définies dans la convention de formation. Les stagiaires doivent respecter les horaires communiqués.</p>

<h2>Article 4 - Évaluation et validation</h2>
<p>L'évaluation des acquis est continue et formative. Les modalités d'évaluation sont communiquées en début de formation.</p>

<h2>Article 5 - Sanctions disciplinaires</h2>
<p>En cas de manquement grave ou répété aux obligations, des sanctions peuvent être appliquées :</p>
<ul>
    <li>Avertissement oral</li>
    <li>Avertissement écrit</li>
    <li>Exclusion temporaire</li>
    <li>Exclusion définitive</li>
</ul>

<h2>Article 6 - Recours et médiation</h2>
<p>Tout stagiaire peut contester une décision en adressant un recours écrit à la direction dans un délai de 15 jours.</p>

<p><em>Contact référent handicap : handicap@eprofos.fr - 01 23 45 67 89</em></p>
HTML;
    }

    private function getStudentHandbookContent(): string
    {
        return <<<HTML
<h1>Livret d'accueil stagiaire</h1>

<h2>Bienvenue chez EPROFOS</h2>
<p>Nous sommes heureux de vous accueillir dans notre centre de formation. Ce livret vous accompagnera tout au long de votre parcours.</p>

<h2>Présentation d'EPROFOS</h2>
<p>EPROFOS est un organisme de formation professionnelle spécialisé dans le développement des compétences techniques et managériales.</p>

<h3>Notre mission</h3>
<ul>
    <li>Accompagner votre montée en compétences</li>
    <li>Adapter nos formations à vos besoins</li>
    <li>Garantir la qualité de nos prestations</li>
</ul>

<h2>Votre équipe pédagogique</h2>
<h3>Direction pédagogique</h3>
<p>Marie Dubois - Directrice pédagogique<br>
Email : marie.dubois@eprofos.fr</p>

<h3>Référent handicap</h3>
<p>Jean Martin - Référent accessibilité<br>
Email : handicap@eprofos.fr<br>
Téléphone : 01 23 45 67 89</p>

<h2>Modalités pédagogiques</h2>
<h3>Méthodes d'enseignement</h3>
<ul>
    <li>Cours magistraux interactifs</li>
    <li>Ateliers pratiques</li>
    <li>Études de cas</li>
    <li>Travaux en groupe</li>
    <li>Autoformation assistée</li>
</ul>

<h3>Supports pédagogiques</h3>
<ul>
    <li>Supports de cours numériques</li>
    <li>Plateforme e-learning</li>
    <li>Bibliothèque de ressources</li>
    <li>Outils collaboratifs</li>
</ul>

<h2>Informations pratiques</h2>
<h3>Horaires</h3>
<p>Lundi au vendredi : 9h00 - 17h30<br>
Pause déjeuner : 12h30 - 13h30</p>

<h3>Accès</h3>
<p>123 Avenue de la Formation<br>
75001 Paris<br>
Métro : Ligne 1 - Station Formation</p>

<h3>Restauration</h3>
<p>Restaurant d'entreprise sur site<br>
Micro-ondes à disposition<br>
Distributeurs de boissons</p>

<h2>Contacts utiles</h2>
<ul>
    <li>Accueil : 01 23 45 67 89</li>
    <li>Support technique : support@eprofos.fr</li>
    <li>Urgences : 06 12 34 56 78</li>
</ul>

<h2>Procédures d'urgence</h2>
<p>En cas d'urgence, contactez immédiatement l'accueil ou composez le 15 (SAMU) ou 18 (Pompiers).</p>

<p><strong>Nous vous souhaitons une excellente formation !</strong></p>
HTML;
    }

    private function getTrainingTermsContent(): string
    {
        return <<<HTML
<h1>Conditions générales de formation</h1>

<h2>Article 1 - Modalités pédagogiques détaillées</h2>
<h3>1.1 Méthodes pédagogiques</h3>
<p>Nos formations utilisent une approche multimodale :</p>
<ul>
    <li><strong>Présentiel :</strong> Cours en face-à-face, ateliers pratiques</li>
    <li><strong>Distanciel :</strong> Classes virtuelles, modules e-learning</li>
    <li><strong>Hybride :</strong> Combinaison présentiel/distanciel</li>
    <li><strong>Accompagnement :</strong> Tutorat individualisé</li>
</ul>

<h3>1.2 Supports pédagogiques</h3>
<ul>
    <li>Manuel de formation numérique</li>
    <li>Exercices pratiques</li>
    <li>Études de cas sectorielles</li>
    <li>Plateforme d'apprentissage en ligne</li>
</ul>

<h2>Article 2 - Modalités d'inscription et d'accès</h2>
<h3>2.1 Prérequis</h3>
<p>Les prérequis sont définis pour chaque formation et communiqués lors de l'inscription.</p>

<h3>2.2 Processus d'inscription</h3>
<ol>
    <li>Entretien de positionnement</li>
    <li>Validation des prérequis</li>
    <li>Signature de la convention</li>
    <li>Convocation</li>
</ol>

<h2>Article 3 - Conditions d'annulation et de report</h2>
<h3>3.1 Annulation par le stagiaire</h3>
<ul>
    <li><strong>Plus de 14 jours :</strong> Remboursement intégral</li>
    <li><strong>Entre 14 et 7 jours :</strong> 50% des frais retenus</li>
    <li><strong>Moins de 7 jours :</strong> 100% des frais retenus</li>
</ul>

<h3>3.2 Annulation par EPROFOS</h3>
<p>En cas d'annulation par EPROFOS, le stagiaire est intégralement remboursé ou une nouvelle session lui est proposée.</p>

<h2>Article 4 - Évaluation et certification</h2>
<h3>4.1 Modalités d'évaluation</h3>
<ul>
    <li>Évaluation continue</li>
    <li>Contrôles intermédiaires</li>
    <li>Évaluation finale</li>
    <li>Mise en situation professionnelle</li>
</ul>

<h3>4.2 Certification</h3>
<p>Une attestation de formation est délivrée à l'issue de la formation, mentionnant :</p>
<ul>
    <li>Les objectifs pédagogiques</li>
    <li>La nature et la durée de l'action</li>
    <li>Les résultats de l'évaluation</li>
</ul>

<h2>Article 5 - Gestion des absences et retards</h2>
<h3>5.1 Absences justifiées</h3>
<p>Les absences doivent être signalées dans les plus brefs délais. Un rattrapage peut être organisé.</p>

<h3>5.2 Retards</h3>
<p>Les retards répétés peuvent entraîner une exclusion temporaire.</p>

<h2>Article 6 - Modalités de financement</h2>
<h3>6.1 Financements acceptés</h3>
<ul>
    <li>CPF (Compte Personnel de Formation)</li>
    <li>OPCO</li>
    <li>Pôle Emploi</li>
    <li>Autofinancement</li>
    <li>Plan de développement des compétences</li>
</ul>

<h2>Article 7 - Réclamations et médiation</h2>
<p>Toute réclamation doit être adressée par écrit à :</p>
<p>Service Qualité EPROFOS<br>
123 Avenue de la Formation<br>
75001 Paris<br>
Email : qualite@eprofos.fr</p>

<p>En cas de litige, une médiation peut être organisée avant tout recours judiciaire.</p>
HTML;
    }

    private function getAccessibilityPolicyContent(): string
    {
        return <<<HTML
<h1>Politique d'accessibilité et d'inclusion</h1>

<h2>Notre engagement</h2>
<p>EPROFOS s'engage à rendre ses formations accessibles à tous, conformément à la loi du 11 février 2005 pour l'égalité des droits et des chances.</p>

<h2>Accessibilité des locaux</h2>
<h3>Nos installations</h3>
<ul>
    <li>Accès PMR (Personne à Mobilité Réduite)</li>
    <li>Ascenseur adapté</li>
    <li>Toilettes accessibles</li>
    <li>Places de parking réservées</li>
    <li>Signalétique adaptée</li>
</ul>

<h2>Adaptations pédagogiques</h2>
<h3>Handicap visuel</h3>
<ul>
    <li>Documents en gros caractères</li>
    <li>Support numérique compatible lecteur d'écran</li>
    <li>Matériel d'agrandissement</li>
    <li>Assistance humaine si nécessaire</li>
</ul>

<h3>Handicap auditif</h3>
<ul>
    <li>Interprète LSF sur demande</li>
    <li>Boucle magnétique</li>
    <li>Supports visuels renforcés</li>
    <li>Transcription écrite des échanges</li>
</ul>

<h3>Handicap moteur</h3>
<ul>
    <li>Adaptation du poste de travail</li>
    <li>Matériel ergonomique</li>
    <li>Horaires aménagés si nécessaire</li>
    <li>Aide à la prise de notes</li>
</ul>

<h3>Handicap mental et psychique</h3>
<ul>
    <li>Rythme adapté</li>
    <li>Pauses supplémentaires</li>
    <li>Accompagnement renforcé</li>
    <li>Supports simplifiés</li>
</ul>

<h2>Procédure de demande d'adaptation</h2>
<ol>
    <li>Contact préalable avec le référent handicap</li>
    <li>Entretien d'évaluation des besoins</li>
    <li>Élaboration du plan d'adaptation</li>
    <li>Mise en œuvre et suivi</li>
</ol>

<h2>Votre référent handicap</h2>
<p><strong>Jean Martin</strong><br>
Référent accessibilité et inclusion<br>
Email : handicap@eprofos.fr<br>
Téléphone : 01 23 45 67 89<br>
Disponible du lundi au vendredi de 9h à 17h</p>

<h2>Partenaires</h2>
<p>Nous travaillons en partenariat avec :</p>
<ul>
    <li>AGEFIPH</li>
    <li>Cap Emploi</li>
    <li>MDPH</li>
    <li>Missions locales</li>
</ul>

<h2>Amélioration continue</h2>
<p>Nous menons une démarche d'amélioration continue de notre accessibilité :</p>
<ul>
    <li>Enquêtes de satisfaction</li>
    <li>Audit annuel d'accessibilité</li>
    <li>Formation des équipes</li>
    <li>Veille réglementaire</li>
</ul>
HTML;
    }

    private function getAccessibilityProceduresContent(): string
    {
        return <<<HTML
<h1>Procédures d'adaptation pédagogique</h1>

<h2>Processus d'accueil et d'évaluation</h2>
<h3>Étape 1 : Premier contact</h3>
<ul>
    <li>Accueil téléphonique ou par email</li>
    <li>Information sur les possibilités d'adaptation</li>
    <li>Prise de rendez-vous avec le référent handicap</li>
</ul>

<h3>Étape 2 : Entretien d'évaluation</h3>
<ul>
    <li>Analyse des besoins spécifiques</li>
    <li>Étude de la faisabilité</li>
    <li>Identification des adaptations nécessaires</li>
    <li>Estimation du coût et recherche de financement</li>
</ul>

<h2>Types d'adaptations possibles</h2>
<h3>Adaptations temporelles</h3>
<ul>
    <li>Horaires aménagés</li>
    <li>Pauses supplémentaires</li>
    <li>Temps majoré pour les évaluations</li>
    <li>Étalement de la formation</li>
</ul>

<h3>Adaptations matérielles</h3>
<ul>
    <li>Poste de travail ergonomique</li>
    <li>Matériel informatique adapté</li>
    <li>Supports en gros caractères</li>
    <li>Outils d'aide à la communication</li>
</ul>

<h3>Adaptations pédagogiques</h3>
<ul>
    <li>Méthodes d'enseignement personnalisées</li>
    <li>Supports visuels renforcés</li>
    <li>Exercices adaptés</li>
    <li>Évaluation sur mesure</li>
</ul>

<h3>Accompagnement humain</h3>
<ul>
    <li>Aide à la prise de notes</li>
    <li>Interprète LSF</li>
    <li>Accompagnateur</li>
    <li>Tutorat renforcé</li>
</ul>

<h2>Financement des adaptations</h2>
<h3>Sources de financement</h3>
<ul>
    <li>AGEFIPH</li>
    <li>FIPHFP (fonction publique)</li>
    <li>Employeur</li>
    <li>OPCO</li>
    <li>Région</li>
</ul>

<h3>Procédure de demande</h3>
<ol>
    <li>Constitution du dossier avec le référent</li>
    <li>Devis des adaptations</li>
    <li>Dépôt de la demande</li>
    <li>Instruction du dossier</li>
    <li>Notification de décision</li>
</ol>

<h2>Suivi et évaluation</h2>
<h3>Pendant la formation</h3>
<ul>
    <li>Points d'étape réguliers</li>
    <li>Ajustement des adaptations</li>
    <li>Résolution des difficultés</li>
    <li>Coordination avec l'équipe pédagogique</li>
</ul>

<h3>À l'issue de la formation</h3>
<ul>
    <li>Bilan de satisfaction</li>
    <li>Évaluation de l'efficacité des adaptations</li>
    <li>Recommandations pour la suite du parcours</li>
    <li>Capitalisation pour améliorer nos pratiques</li>
</ul>

<h2>Contacts et ressources</h2>
<h3>Référent handicap EPROFOS</h3>
<p>Jean Martin<br>
Email : handicap@eprofos.fr<br>
Téléphone : 01 23 45 67 89</p>

<h3>Ressources externes</h3>
<ul>
    <li>AGEFIPH : 0 800 11 10 09</li>
    <li>Cap Emploi : www.capemploi.net</li>
    <li>MDPH de votre département</li>
</ul>
HTML;
    }

    private function getAccessibilityFaqContent(): string
    {
        return <<<HTML
<h1>FAQ Accessibilité et handicap</h1>

<h2>Questions générales</h2>
<h3>Q : Puis-je suivre une formation chez EPROFOS si j'ai un handicap ?</h3>
<p><strong>R :</strong> Absolument ! EPROFOS accueille tous les publics et met en place les adaptations nécessaires pour garantir l'accessibilité de ses formations.</p>

<h3>Q : Dois-je mentionner mon handicap lors de l'inscription ?</h3>
<p><strong>R :</strong> C'est recommandé mais pas obligatoire. Plus nous connaissons vos besoins tôt, mieux nous pouvons préparer votre accueil et les adaptations nécessaires.</p>

<h3>Q : Mes informations sur le handicap resteront-elles confidentielles ?</h3>
<p><strong>R :</strong> Oui, nous respectons strictement la confidentialité. Seules les personnes directement impliquées dans votre formation seront informées, et uniquement des éléments nécessaires.</p>

<h2>Accessibilité des locaux</h2>
<h3>Q : Vos locaux sont-ils accessibles en fauteuil roulant ?</h3>
<p><strong>R :</strong> Oui, nos locaux sont entièrement accessibles : rampe d'accès, ascenseur, toilettes adaptées, places de parking réservées.</p>

<h3>Q : Y a-t-il des places de parking pour PMR ?</h3>
<p><strong>R :</strong> Oui, 3 places de parking PMR sont disponibles à proximité immédiate de l'entrée.</p>

<h2>Adaptations pédagogiques</h2>
<h3>Q : Pouvez-vous adapter les supports de cours ?</h3>
<p><strong>R :</strong> Oui, nous adaptons les supports selon vos besoins : gros caractères, format numérique compatible lecteur d'écran, version simplifiée, etc.</p>

<h3>Q : Est-il possible d'avoir un interprète LSF ?</h3>
<p><strong>R :</strong> Oui, sur demande préalable. Nous organisons la présence d'un interprète LSF. Cette prestation peut être financée par l'AGEFIPH.</p>

<h3>Q : Puis-je bénéficier de temps supplémentaire pour les évaluations ?</h3>
<p><strong>R :</strong> Oui, nous pouvons majorer le temps d'évaluation selon vos besoins, généralement de 30% à 50%.</p>

<h2>Financement</h2>
<h3>Q : Qui finance les adaptations nécessaires ?</h3>
<p><strong>R :</strong> Les adaptations peuvent être financées par l'AGEFIPH, votre employeur, l'OPCO, ou d'autres organismes. Notre référent vous aide dans les démarches.</p>

<h3>Q : Les adaptations ont-elles un coût pour moi ?</h3>
<p><strong>R :</strong> Non, les adaptations liées au handicap sont généralement prises en charge par des organismes dédiés. Vous ne devez pas supporter ce coût.</p>

<h2>Procédures</h2>
<h3>Q : Quand dois-je contacter le référent handicap ?</h3>
<p><strong>R :</strong> Le plus tôt possible, idéalement lors de votre inscription. Cela nous permet de préparer au mieux votre accueil.</p>

<h3>Q : Combien de temps avant la formation dois-je faire ma demande ?</h3>
<p><strong>R :</strong> Idéalement 3 semaines avant le début de la formation pour permettre la mise en place des adaptations.</p>

<h2>Types de handicap</h2>
<h3>Q : Accueillez-vous les personnes avec un handicap psychique ?</h3>
<p><strong>R :</strong> Oui, nous adaptons le rythme, proposons un accompagnement renforcé et créons un environnement bienveillant.</p>

<h3>Q : Comment se passe l'accueil pour un handicap invisible ?</h3>
<p><strong>R :</strong> Nous sommes sensibilisés aux handicaps invisibles (dyslexie, fibromyalgie, etc.) et adaptons discrètement nos méthodes pédagogiques.</p>

<h2>Contact</h2>
<h3>Q : Comment contacter le référent handicap ?</h3>
<p><strong>R :</strong> 
<br>Jean Martin - Référent accessibilité
<br>Email : handicap@eprofos.fr
<br>Téléphone : 01 23 45 67 89
<br>Disponible du lundi au vendredi de 9h à 17h</p>

<h3>Q : Puis-je visiter les locaux avant ma formation ?</h3>
<p><strong>R :</strong> Bien sûr ! Contactez-nous pour organiser une visite et vous familiariser avec l'environnement de formation.</p>

<p><em>Cette FAQ est mise à jour régulièrement. N'hésitez pas à nous contacter pour toute question non traitée ici.</em></p>
HTML;
    }
}
