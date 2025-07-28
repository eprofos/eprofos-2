<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Document\Document;
use App\Entity\Document\DocumentCategory;
use App\Entity\Document\DocumentType;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

/**
 * Document Fixtures - Creates comprehensive test documents.
 *
 * Provides realistic test data for the Document Management System,
 * including various document types with proper relationships to
 * categories and types for testing and demonstration purposes.
 */
class DocumentFixtures extends Fixture implements DependentFixtureInterface
{
    public const CGVF_DOCUMENT_REFERENCE = 'document-cgvf';

    public const ACCESSIBILITY_POLICY_REFERENCE = 'document-accessibility-policy';

    public const QUALITY_MANUAL_REFERENCE = 'document-quality-manual';

    public const STUDENT_HANDBOOK_REFERENCE = 'document-student-handbook';

    public const INTERNAL_REGULATIONS_REFERENCE = 'document-internal-regulations';

    public const PRIVACY_POLICY_REFERENCE = 'document-privacy-policy';

    public const COURSE_CATALOG_REFERENCE = 'document-course-catalog';

    public const EVALUATION_PROCEDURE_REFERENCE = 'document-evaluation-procedure';

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        $documents = [
            [
                'title' => 'Conditions Générales de Vente de Formation (CGVF)',
                'slug' => 'conditions-generales-vente-formation',
                'description' => 'Conditions générales applicables à toutes nos formations professionnelles, définissant les droits et obligations des parties.',
                'content' => $this->getCgvfContent(),
                'documentType' => DocumentTypeFixtures::TERMS_TYPE_REFERENCE,
                'category' => DocumentCategoryFixtures::LEGAL_CATEGORY_REFERENCE,
                'status' => Document::STATUS_PUBLISHED,
                'isActive' => true,
                'isPublic' => true,
                'version' => '2.1',
                'tags' => ['cgvf', 'formation', 'conditions', 'légal'],
                'publishedAt' => new DateTimeImmutable('-3 months'),
                'reference' => self::CGVF_DOCUMENT_REFERENCE,
            ],
            [
                'title' => 'Politique d\'Accessibilité Numérique',
                'slug' => 'politique-accessibilite-numerique',
                'description' => 'Notre engagement pour l\'accessibilité numérique et l\'inclusion des personnes en situation de handicap.',
                'content' => $this->getAccessibilityPolicyContent(),
                'documentType' => DocumentTypeFixtures::ACCESSIBILITY_TYPE_REFERENCE,
                'category' => DocumentCategoryFixtures::ACCESSIBILITY_CATEGORY_REFERENCE,
                'status' => Document::STATUS_PUBLISHED,
                'isActive' => true,
                'isPublic' => true,
                'version' => '1.3',
                'tags' => ['accessibilité', 'handicap', 'inclusion', 'numérique'],
                'publishedAt' => new DateTimeImmutable('-2 months'),
                'reference' => self::ACCESSIBILITY_POLICY_REFERENCE,
            ],
            [
                'title' => 'Manuel Qualité Qualiopi',
                'slug' => 'manuel-qualite-qualiopi',
                'description' => 'Manuel du système de management de la qualité conforme aux exigences Qualiopi.',
                'content' => $this->getQualityManualContent(),
                'documentType' => DocumentTypeFixtures::QUALITY_TYPE_REFERENCE,
                'category' => DocumentCategoryFixtures::QUALITY_CATEGORY_REFERENCE,
                'status' => Document::STATUS_PUBLISHED,
                'isActive' => true,
                'isPublic' => false,
                'version' => '3.0',
                'tags' => ['qualité', 'qualiopi', 'certification', 'management'],
                'publishedAt' => new DateTimeImmutable('-1 month'),
                'expiresAt' => new DateTimeImmutable('+11 months'),
                'reference' => self::QUALITY_MANUAL_REFERENCE,
            ],
            [
                'title' => 'Livret d\'Accueil Apprenant 2025',
                'slug' => 'livret-accueil-apprenant-2025',
                'description' => 'Guide complet pour l\'accueil et l\'orientation des nouveaux apprenants pour l\'année 2025.',
                'content' => $this->getStudentHandbookContent(),
                'documentType' => DocumentTypeFixtures::HANDBOOK_TYPE_REFERENCE,
                'category' => DocumentCategoryFixtures::STUDENT_CATEGORY_REFERENCE,
                'status' => Document::STATUS_PUBLISHED,
                'isActive' => true,
                'isPublic' => true,
                'version' => '1.0',
                'tags' => ['accueil', 'apprenant', 'orientation', '2025'],
                'publishedAt' => new DateTimeImmutable('-2 weeks'),
                'reference' => self::STUDENT_HANDBOOK_REFERENCE,
            ],
            [
                'title' => 'Règlement Intérieur Formation',
                'slug' => 'reglement-interieur-formation',
                'description' => 'Règlement intérieur applicable aux apprenants en formation professionnelle.',
                'content' => $this->getInternalRegulationsContent(),
                'documentType' => DocumentTypeFixtures::LEGAL_TYPE_REFERENCE,
                'category' => DocumentCategoryFixtures::REGULATORY_CATEGORY_REFERENCE,
                'status' => Document::STATUS_PUBLISHED,
                'isActive' => true,
                'isPublic' => true,
                'version' => '1.5',
                'tags' => ['règlement', 'formation', 'apprenant', 'discipline'],
                'publishedAt' => new DateTimeImmutable('-6 months'),
                'reference' => self::INTERNAL_REGULATIONS_REFERENCE,
            ],
            [
                'title' => 'Politique de Confidentialité et Protection des Données',
                'slug' => 'politique-confidentialite-rgpd',
                'description' => 'Notre politique de protection des données personnelles conforme au RGPD.',
                'content' => $this->getPrivacyPolicyContent(),
                'documentType' => DocumentTypeFixtures::LEGAL_TYPE_REFERENCE,
                'category' => DocumentCategoryFixtures::LEGAL_CATEGORY_REFERENCE,
                'status' => Document::STATUS_PUBLISHED,
                'isActive' => true,
                'isPublic' => true,
                'version' => '2.0',
                'tags' => ['rgpd', 'confidentialité', 'données', 'protection'],
                'publishedAt' => new DateTimeImmutable('-4 months'),
                'reference' => self::PRIVACY_POLICY_REFERENCE,
            ],
            [
                'title' => 'Catalogue de Formations 2025',
                'slug' => 'catalogue-formations-2025',
                'description' => 'Catalogue complet de nos formations professionnelles pour l\'année 2025.',
                'content' => $this->getCourseCatalogContent(),
                'documentType' => DocumentTypeFixtures::TRAINING_TYPE_REFERENCE,
                'category' => DocumentCategoryFixtures::TRAINING_CATEGORY_REFERENCE,
                'status' => Document::STATUS_PUBLISHED,
                'isActive' => true,
                'isPublic' => true,
                'version' => '1.0',
                'tags' => ['catalogue', 'formations', '2025', 'offre'],
                'publishedAt' => new DateTimeImmutable('-1 week'),
                'reference' => self::COURSE_CATALOG_REFERENCE,
            ],
            [
                'title' => 'Procédure d\'Évaluation des Apprentissages',
                'slug' => 'procedure-evaluation-apprentissages',
                'description' => 'Procédure détaillée pour l\'évaluation des compétences et des apprentissages.',
                'content' => $this->getEvaluationProcedureContent(),
                'documentType' => DocumentTypeFixtures::PROCEDURE_TYPE_REFERENCE,
                'category' => DocumentCategoryFixtures::QUALITY_CATEGORY_REFERENCE,
                'status' => Document::STATUS_PUBLISHED,
                'isActive' => true,
                'isPublic' => false,
                'version' => '1.2',
                'tags' => ['évaluation', 'compétences', 'apprentissage', 'procédure'],
                'publishedAt' => new DateTimeImmutable('-3 weeks'),
                'reference' => self::EVALUATION_PROCEDURE_REFERENCE,
            ],
        ];

        foreach ($documents as $docData) {
            $document = new Document();
            $document->setTitle($docData['title'])
                ->setSlug($docData['slug'])
                ->setDescription($docData['description'])
                ->setContent($docData['content'])
                ->setDocumentType($this->getReference($docData['documentType'], DocumentType::class))
                ->setCategory($this->getReference($docData['category'], DocumentCategory::class))
                ->setStatus($docData['status'])
                ->setIsActive($docData['isActive'])
                ->setIsPublic($docData['isPublic'])
                ->setVersion($docData['version'])
                ->setTags($docData['tags'])
                ->setPublishedAt($docData['publishedAt'])
            ;

            if (isset($docData['expiresAt'])) {
                $document->setExpiresAt($docData['expiresAt']);
            }

            // Set download count for some documents
            if ($docData['isPublic']) {
                $document->setDownloadCount($faker->numberBetween(50, 500));
            }

            $manager->persist($document);
            $this->addReference($docData['reference'], $document);
        }

        // Create some draft documents
        $draftDocuments = [
            [
                'title' => 'Nouvelle Procédure Inscription en ligne',
                'slug' => 'procedure-inscription-en-ligne-draft',
                'description' => 'Procédure en cours de rédaction pour l\'inscription en ligne des apprenants.',
                'content' => '<div class="document-content"><h1>Nouvelle Procédure d\'Inscription en ligne</h1><p class="text-muted"><em>Contenu en cours de rédaction...</em></p><div class="alert alert-warning"><p>📝 Ce document est actuellement en cours de rédaction par l\'équipe pédagogique.</p></div></div>',
                'documentType' => DocumentTypeFixtures::PROCEDURE_TYPE_REFERENCE,
                'category' => DocumentCategoryFixtures::ADMIN_CATEGORY_REFERENCE,
                'status' => Document::STATUS_DRAFT,
            ],
            [
                'title' => 'Politique RSE en révision',
                'slug' => 'politique-rse-revision',
                'description' => 'Révision de notre politique de responsabilité sociétale d\'entreprise.',
                'content' => '<div class="document-content"><h1>Politique RSE</h1><p class="text-info"><em>Révision en cours suite aux nouvelles réglementations...</em></p><div class="alert alert-info"><p>🔄 Ce document est en cours de révision pour intégrer les nouvelles exigences réglementaires en matière de RSE.</p></div></div>',
                'documentType' => DocumentTypeFixtures::POLICY_TYPE_REFERENCE,
                'category' => DocumentCategoryFixtures::INTERNAL_CATEGORY_REFERENCE,
                'status' => Document::STATUS_REVIEW,
            ],
        ];

        foreach ($draftDocuments as $docData) {
            $document = new Document();
            $document->setTitle($docData['title'])
                ->setSlug($docData['slug'])
                ->setDescription($docData['description'])
                ->setContent($docData['content'])
                ->setDocumentType($this->getReference($docData['documentType'], DocumentType::class))
                ->setCategory($this->getReference($docData['category'], DocumentCategory::class))
                ->setStatus($docData['status'])
                ->setIsActive(true)
                ->setIsPublic(false)
                ->setVersion('0.1')
                ->setTags(['draft', 'en-cours'])
            ;

            $manager->persist($document);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            DocumentTypeFixtures::class,
            DocumentCategoryFixtures::class,
        ];
    }

    private function getCgvfContent(): string
    {
        return <<<'EOF'
<div class="document-content">
    <h1>CONDITIONS GÉNÉRALES DE VENTE DE FORMATION (CGVF)</h1>

    <article class="document-article">
        <h2>Article 1 - Objet et champ d'application</h2>
        <p>Les présentes Conditions Générales de Vente de Formation (CGVF) régissent les relations contractuelles entre <strong>EPROFOS</strong>, organisme de formation professionnelle déclaré sous le numéro <em>11756123456</em> auprès du préfet de région Île-de-France, et ses clients.</p>
    </article>

    <article class="document-article">
        <h2>Article 2 - Inscriptions</h2>
        
        <section>
            <h3>2.1 Modalités d'inscription</h3>
            <p>L'inscription aux formations est effective après :</p>
            <ul>
                <li>Réception du bulletin d'inscription signé</li>
                <li>Versement de l'acompte ou du règlement intégral</li>
                <li>Validation des prérequis le cas échéant</li>
            </ul>
        </section>

        <section>
            <h3>2.2 Confirmation d'inscription</h3>
            <p>Une convention de formation ou un contrat de formation professionnelle sera établi conformément aux articles L. 6353-1 et suivants du Code du travail.</p>
        </section>
    </article>

    <article class="document-article">
        <h2>Article 3 - Tarifs et modalités de paiement</h2>
        
        <section>
            <h3>3.1 Tarifs</h3>
            <p>Les tarifs sont exprimés en euros HT et TTC. Ils sont valables pour l'année en cours et peuvent être révisés sans préavis.</p>
        </section>

        <section>
            <h3>3.2 Modalités de paiement</h3>
            <ul>
                <li><strong>Particuliers</strong> : 30% à l'inscription, solde 15 jours avant le début de formation</li>
                <li><strong>Entreprises</strong> : Facturation après signature de la convention</li>
                <li><strong>CPF</strong> : Paiement direct par la CDC</li>
            </ul>
        </section>
    </article>

    <article class="document-article">
        <h2>Article 4 - Annulation et report</h2>
        
        <section>
            <h3>4.1 Annulation par le stagiaire</h3>
            <ul>
                <li><strong>Plus de 30 jours</strong> : remboursement intégral moins 150€ de frais de dossier</li>
                <li><strong>Entre 15 et 30 jours</strong> : 50% du montant versé</li>
                <li><strong>Moins de 15 jours</strong> : aucun remboursement</li>
            </ul>
        </section>

        <section>
            <h3>4.2 Annulation par EPROFOS</h3>
            <p>En cas d'annulation de notre fait, les sommes versées sont intégralement remboursées.</p>
        </section>
    </article>

    <article class="document-article">
        <h2>Article 5 - Obligations des parties</h2>
        
        <section>
            <h3>5.1 Obligations d'EPROFOS</h3>
            <ul>
                <li>Dispenser la formation conformément au programme</li>
                <li>Fournir les supports pédagogiques</li>
                <li>Délivrer une attestation de fin de formation</li>
            </ul>
        </section>

        <section>
            <h3>5.2 Obligations du stagiaire</h3>
            <ul>
                <li>Respecter le règlement intérieur</li>
                <li>Participer activement à la formation</li>
                <li>Respecter les autres participants et formateurs</li>
            </ul>
        </section>
    </article>

    <article class="document-article">
        <h2>Article 6 - Protection des données</h2>
        <p>Conformément au <abbr title="Règlement Général sur la Protection des Données">RGPD</abbr>, les données personnelles collectées sont utilisées uniquement dans le cadre de la gestion de la formation et peuvent faire l'objet d'un droit d'accès, de rectification et de suppression.</p>
    </article>

    <article class="document-article">
        <h2>Article 7 - Litiges</h2>
        <p>Tout litige sera soumis aux tribunaux compétents du siège social d'EPROFOS.</p>
    </article>

    <footer class="document-footer">
        <hr>
        <p class="text-muted"><em>Document mis à jour le 19 juillet 2025</em></p>
    </footer>
</div>
EOF;
    }

    private function getAccessibilityPolicyContent(): string
    {
        return <<<'EOF'
<div class="document-content">
    <h1>POLITIQUE D'ACCESSIBILITÉ NUMÉRIQUE</h1>

    <section class="document-section">
        <h2>Notre engagement</h2>
        <p><strong>EPROFOS</strong> s'engage à rendre ses services numériques accessibles conformément à l'article 47 de la loi n°2005-102 du 11 février 2005.</p>
    </section>

    <section class="document-section">
        <h2>Déclaration d'accessibilité</h2>
        <p>Cette déclaration s'applique au site web <strong>eprofos.fr</strong> et à notre plateforme de formation en ligne.</p>

        <h3>État de conformité</h3>
        <p>Le site eprofos.fr est <span class="badge bg-warning">partiellement conforme</span> avec le RGAA 4.1.</p>

        <h3>Contenus non accessibles</h3>
        <p>Les contenus listés ci-dessous ne sont pas accessibles pour les raisons suivantes :</p>
        <ul>
            <li>Certaines vidéos de formation ne disposent pas encore de sous-titres</li>
            <li>Quelques documents PDF anciens ne respectent pas les standards d'accessibilité</li>
        </ul>
    </section>

    <section class="document-section">
        <h2>Amélioration et contact</h2>

        <h3>Plan d'amélioration</h3>
        <div class="timeline">
            <div class="timeline-item">
                <strong>Mars 2025</strong> : Ajout de sous-titres sur toutes les vidéos
            </div>
            <div class="timeline-item">
                <strong>Juin 2025</strong> : Mise en conformité des documents PDF
            </div>
            <div class="timeline-item">
                <strong>Septembre 2025</strong> : Audit complet d'accessibilité
            </div>
        </div>

        <h3>Nous contacter</h3>
        <p>Si vous rencontrez un défaut d'accessibilité :</p>
        <div class="contact-info">
            <p><i class="fas fa-envelope"></i> <strong>Email</strong> : <a href="mailto:accessibilite@eprofos.fr">accessibilite@eprofos.fr</a></p>
            <p><i class="fas fa-phone"></i> <strong>Téléphone</strong> : <a href="tel:0123456789">01 23 45 67 89</a></p>
            <p><i class="fas fa-map-marker-alt"></i> <strong>Courrier</strong> : EPROFOS - Accessibilité, 123 Rue de la Formation, 75001 Paris</p>
        </div>

        <h3>Voies de recours</h3>
        <p>Si nos réponses ne vous satisfont pas, vous pouvez :</p>
        <ul>
            <li>Écrire un message au Défenseur des droits</li>
            <li>Contacter le délégué du Défenseur des droits dans votre région</li>
            <li>Envoyer un courrier par la poste (gratuit, ne pas mettre de timbre)</li>
        </ul>
    </section>

    <footer class="document-footer">
        <hr>
        <p class="text-muted"><em>Dernière mise à jour : 19 juillet 2025</em></p>
    </footer>
</div>
EOF;
    }

    private function getQualityManualContent(): string
    {
        return <<<'EOF'
<div class="document-content">
    <h1>MANUEL QUALITÉ QUALIOPI</h1>

    <section class="document-section">
        <h2>1. Présentation de l'organisme</h2>
        <p><strong>EPROFOS</strong> est un organisme de formation professionnelle certifié Qualiopi depuis 2023.</p>

        <h3>1.1 Domaines d'activité</h3>
        <ul>
            <li>Formation professionnelle continue</li>
            <li>Accompagnement et conseil</li>
            <li>Certification professionnelle</li>
        </ul>

        <h3>1.2 Public visé</h3>
        <ul>
            <li>Salariés en reconversion</li>
            <li>Demandeurs d'emploi</li>
            <li>Entrepreneurs et indépendants</li>
        </ul>
    </section>

    <section class="document-section">
        <h2>2. Politique qualité</h2>
        <p>Notre politique qualité s'articule autour de 7 critères Qualiopi :</p>

        <div class="quality-criteria">
            <div class="criterion">
                <h3>Critère 1 : Information du public</h3>
                <p>Nous garantissons une information claire et accessible sur :</p>
                <ul>
                    <li>Les objectifs pédagogiques</li>
                    <li>Les prérequis</li>
                    <li>Les modalités d'évaluation</li>
                    <li>Les débouchés professionnels</li>
                </ul>
            </div>

            <div class="criterion">
                <h3>Critère 2 : Identification des objectifs</h3>
                <p>Chaque formation dispose d'objectifs :</p>
                <ul>
                    <li>Mesurables</li>
                    <li>Atteignables</li>
                    <li>Pertinents</li>
                    <li>Temporellement définis</li>
                </ul>
            </div>

            <div class="criterion">
                <h3>Critère 3 : Adaptation aux publics</h3>
                <p>Nous adaptons nos formations selon :</p>
                <ul>
                    <li>Le niveau des apprenants</li>
                    <li>Leurs contraintes professionnelles</li>
                    <li>Leurs besoins spécifiques</li>
                </ul>
            </div>

            <div class="criterion">
                <h3>Critère 4 : Adéquation moyens/prestations</h3>
                <p>Nos moyens pédagogiques sont adaptés :</p>
                <ul>
                    <li>Locaux accessibles et équipés</li>
                    <li>Formateurs experts métier</li>
                    <li>Supports actualisés</li>
                </ul>
            </div>

            <div class="criterion">
                <h3>Critère 5 : Qualification des formateurs</h3>
                <p>Nos formateurs justifient :</p>
                <ul>
                    <li>D'une expertise métier reconnue</li>
                    <li>D'une formation pédagogique</li>
                    <li>D'une mise à jour régulière de leurs compétences</li>
                </ul>
            </div>

            <div class="criterion">
                <h3>Critère 6 : Inscription dans l'environnement</h3>
                <p>Nous entretenons des relations avec :</p>
                <ul>
                    <li>Les branches professionnelles</li>
                    <li>Les OPCO</li>
                    <li>Les entreprises partenaires</li>
                </ul>
            </div>

            <div class="criterion">
                <h3>Critère 7 : Recueil des appréciations</h3>
                <p>Nous évaluons systématiquement :</p>
                <ul>
                    <li>La satisfaction des apprenants</li>
                    <li>L'atteinte des objectifs</li>
                    <li>L'insertion professionnelle</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="document-section">
        <h2>3. Amélioration continue</h2>
        <p>Notre démarche d'amélioration continue s'appuie sur :</p>
        <ul>
            <li>Les évaluations à chaud et à froid</li>
            <li>Les retours des formateurs</li>
            <li>Les audits qualité internes</li>
            <li>Le suivi des indicateurs de performance</li>
        </ul>
    </section>

    <footer class="document-footer">
        <hr>
        <div class="version-info">
            <p><strong>Version 3.0</strong> - Juillet 2025</p>
            <p class="text-muted"><em>Prochaine révision : Juillet 2026</em></p>
        </div>
    </footer>
</div>
EOF;
    }

    private function getStudentHandbookContent(): string
    {
        return <<<'EOF'
<div class="document-content">
    <h1>LIVRET D'ACCUEIL APPRENANT 2025</h1>
    
    <div class="welcome-message bg-primary text-white p-4 rounded mb-4">
        <h2 class="h3 mb-3">🎓 Bienvenue chez EPROFOS !</h2>
        <p class="mb-0">Ce livret vous accompagne dans vos premiers pas.</p>
    </div>

    <section class="document-section">
        <h2>Présentation d'EPROFOS</h2>

        <h3>Qui sommes-nous ?</h3>
        <p><strong>EPROFOS</strong> est un organisme de formation professionnelle spécialisé dans les métiers du numérique et de la transition écologique.</p>

        <h3>Nos valeurs</h3>
        <div class="values-grid">
            <div class="value-item">
                <h4>🏆 Excellence</h4>
                <p>Des formations de haute qualité</p>
            </div>
            <div class="value-item">
                <h4>💡 Innovation</h4>
                <p>Pédagogie moderne et interactive</p>
            </div>
            <div class="value-item">
                <h4>🤝 Inclusion</h4>
                <p>Accès pour tous, adaptation aux besoins</p>
            </div>
            <div class="value-item">
                <h4>🌱 Durabilité</h4>
                <p>Engagement écologique et social</p>
            </div>
        </div>
    </section>

    <section class="document-section">
        <h2>Votre parcours de formation</h2>

        <div class="journey-steps">
            <div class="step">
                <h3>📋 Avant le début</h3>
                <ol>
                    <li>Entretien de positionnement</li>
                    <li>Définition du projet professionnel</li>
                    <li>Validation des prérequis</li>
                    <li>Signature de la convention</li>
                </ol>
            </div>

            <div class="step">
                <h3>🎯 Pendant la formation</h3>
                <ul>
                    <li>Suivi pédagogique individualisé</li>
                    <li>Évaluations régulières</li>
                    <li>Accompagnement projet</li>
                    <li>Accès aux ressources numériques</li>
                </ul>
            </div>

            <div class="step">
                <h3>🚀 Après la formation</h3>
                <ul>
                    <li>Attestation de fin de formation</li>
                    <li>Suivi à 3 et 6 mois</li>
                    <li>Accompagnement à l'emploi</li>
                    <li>Réseau alumni</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="document-section">
        <h2>Informations pratiques</h2>

        <div class="practical-info">
            <div class="info-block">
                <h3>🕒 Horaires</h3>
                <ul>
                    <li><strong>Lundi au vendredi</strong> : 9h00 - 17h30</li>
                    <li><strong>Accueil</strong> : 8h30 - 18h00</li>
                </ul>
            </div>

            <div class="info-block">
                <h3>📍 Locaux</h3>
                <div class="address">
                    <h4>Siège social</h4>
                    <address>
                        123 Rue de la Formation<br>
                        75001 Paris
                    </address>
                </div>
                <div class="address">
                    <h4>Campus numérique</h4>
                    <address>
                        456 Avenue de l'Innovation<br>
                        92100 Boulogne-Billancourt
                    </address>
                </div>
            </div>

            <div class="info-block">
                <h3>🛠️ Services</h3>
                <ul>
                    <li>WiFi gratuit</li>
                    <li>Espace détente</li>
                    <li>Restauration sur place</li>
                    <li>Parking visiteurs</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="document-section">
        <h2>Vos interlocuteurs</h2>

        <div class="contacts">
            <div class="team-section">
                <h3>👥 Équipe pédagogique</h3>
                <ul>
                    <li><strong>Directeur pédagogique</strong> : Jean MARTIN</li>
                    <li><strong>Coordinatrice formations</strong> : Marie DUBOIS</li>
                    <li><strong>Référent handicap</strong> : Pierre BERNARD</li>
                </ul>
            </div>

            <div class="contact-section">
                <h3>📞 Contacts utiles</h3>
                <div class="contact-info">
                    <p><i class="fas fa-phone"></i> <strong>Accueil</strong> : <a href="tel:0123456789">01 23 45 67 89</a></p>
                    <p><i class="fas fa-envelope"></i> <strong>Email</strong> : <a href="mailto:contact@eprofos.fr">contact@eprofos.fr</a></p>
                    <p><i class="fas fa-exclamation-triangle"></i> <strong>Urgences</strong> : <a href="tel:0612345678">06 12 34 56 78</a></p>
                </div>
            </div>
        </div>
    </section>

    <section class="document-section">
        <h2>Droits et devoirs</h2>

        <div class="rights-duties">
            <div class="rights">
                <h3>✅ Vos droits</h3>
                <ul>
                    <li>Formation de qualité</li>
                    <li>Accompagnement personnalisé</li>
                    <li>Accès aux équipements</li>
                    <li>Respect de la confidentialité</li>
                </ul>
            </div>

            <div class="duties">
                <h3>📝 Vos devoirs</h3>
                <ul>
                    <li>Assiduité et ponctualité</li>
                    <li>Respect du règlement intérieur</li>
                    <li>Participation active</li>
                    <li>Bienveillance envers tous</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="document-section">
        <h2>Financement et démarches</h2>

        <div class="financing-options">
            <div class="option">
                <h3>💰 CPF (Compte Personnel de Formation)</h3>
                <p>Connectez-vous sur <a href="https://moncompteformation.gouv.fr" target="_blank">moncompteformation.gouv.fr</a></p>
            </div>

            <div class="option">
                <h3>🏢 Pôle emploi</h3>
                <p>Contactez votre conseiller référent</p>
            </div>

            <div class="option">
                <h3>👔 Employeur</h3>
                <p>Convention de formation tripartite</p>
            </div>

            <div class="option">
                <h3>🏛️ Région</h3>
                <p>Dispositifs spécifiques selon votre situation</p>
            </div>
        </div>
    </section>

    <footer class="document-footer">
        <hr>
        <div class="text-center">
            <h3 class="text-primary">🎉 Bonne formation avec EPROFOS !</h3>
            <p class="text-muted"><em>Document mis à jour : Juillet 2025</em></p>
        </div>
    </footer>
</div>
EOF;
    }

    private function getInternalRegulationsContent(): string
    {
        return <<<'EOF'
<div class="document-content">
    <h1>RÈGLEMENT INTÉRIEUR FORMATION</h1>

    <article class="document-article">
        <h2>Article 1 - Objet et champ d'application</h2>
        <p>Le présent règlement s'applique à toutes les personnes participant aux formations dispensées par <strong>EPROFOS</strong>.</p>
    </article>

    <article class="document-article">
        <h2>Article 2 - Dispositions générales</h2>
        
        <section>
            <h3>2.1 Accès aux locaux</h3>
            <ul>
                <li>Présentation obligatoire de la carte d'accès</li>
                <li>Respect des consignes de sécurité</li>
                <li>Interdiction de fumer dans l'enceinte</li>
            </ul>
        </section>

        <section>
            <h3>2.2 Horaires</h3>
            <ul>
                <li>Respect strict des horaires de formation</li>
                <li>En cas de retard, prévenir le formateur</li>
                <li><strong>Pause déjeuner</strong> : 12h00 - 13h30</li>
            </ul>
        </section>
    </article>

    <article class="document-article">
        <h2>Article 3 - Discipline et comportement</h2>
        
        <section>
            <h3>3.1 Obligations</h3>
            <ul>
                <li>Assiduité et ponctualité</li>
                <li>Participation active aux activités</li>
                <li>Respect des autres participants</li>
                <li>Utilisation appropriée du matériel</li>
            </ul>
        </section>

        <section>
            <h3>3.2 Interdictions</h3>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <li>Usage d'alcool ou de stupéfiants</li>
                    <li>Comportement violent ou discriminatoire</li>
                    <li>Utilisation personnelle du matériel informatique</li>
                    <li>Diffusion non autorisée des contenus de formation</li>
                </ul>
            </div>
        </section>
    </article>

    <article class="document-article">
        <h2>Article 4 - Hygiène et sécurité</h2>
        
        <section>
            <h3>4.1 Consignes de sécurité</h3>
            <ul>
                <li>Respect des issues de secours</li>
                <li>Port des EPI si requis</li>
                <li>Signalement immédiat des incidents</li>
            </ul>
        </section>

        <section>
            <h3>4.2 Accident</h3>
            <div class="emergency-procedure">
                <p><strong>En cas d'accident :</strong></p>
                <ol>
                    <li>Alerter immédiatement l'accueil</li>
                    <li>Ne pas déplacer la victime</li>
                    <li>Faciliter l'intervention des secours</li>
                </ol>
            </div>
        </section>
    </article>

    <article class="document-article">
        <h2>Article 5 - Utilisation du matériel</h2>
        
        <section>
            <h3>5.1 Matériel informatique</h3>
            <ul>
                <li>Usage strictement pédagogique</li>
                <li>Respect de la charte informatique</li>
                <li>Signalement des dysfonctionnements</li>
            </ul>
        </section>

        <section>
            <h3>5.2 Espaces communs</h3>
            <ul>
                <li>Maintenir la propreté</li>
                <li>Ranger après utilisation</li>
                <li>Respecter les autres utilisateurs</li>
            </ul>
        </section>
    </article>

    <article class="document-article">
        <h2>Article 6 - Évaluation et certification</h2>
        
        <section>
            <h3>6.1 Contrôle des connaissances</h3>
            <ul>
                <li>Participation obligatoire aux évaluations</li>
                <li>Respect des consignes d'examen</li>
                <li>Interdiction de fraude</li>
            </ul>
        </section>

        <section>
            <h3>6.2 Certification</h3>
            <ul>
                <li>Conditions de délivrance clairement définies</li>
                <li>Recours possible en cas de litige</li>
                <li>Conservation des résultats</li>
            </ul>
        </section>
    </article>

    <article class="document-article">
        <h2>Article 7 - Sanctions disciplinaires</h2>
        
        <section>
            <h3>7.1 Sanctions possibles</h3>
            <div class="sanctions-list">
                <div class="sanction-level">
                    <span class="badge bg-warning">Niveau 1</span> Avertissement oral
                </div>
                <div class="sanction-level">
                    <span class="badge bg-warning">Niveau 2</span> Avertissement écrit
                </div>
                <div class="sanction-level">
                    <span class="badge bg-danger">Niveau 3</span> Exclusion temporaire
                </div>
                <div class="sanction-level">
                    <span class="badge bg-danger">Niveau 4</span> Exclusion définitive
                </div>
            </div>
        </section>

        <section>
            <h3>7.2 Procédure</h3>
            <p>Toute sanction fait l'objet :</p>
            <ul>
                <li>D'un entretien préalable</li>
                <li>D'une notification écrite</li>
                <li>D'un délai de recours</li>
            </ul>
        </section>
    </article>

    <article class="document-article">
        <h2>Article 8 - Réclamations</h2>
        <p>Toute réclamation peut être adressée :</p>
        <ul>
            <li>Au responsable pédagogique</li>
            <li>À la direction de l'organisme</li>
            <li>Au médiateur externe si nécessaire</li>
        </ul>
    </article>

    <article class="document-article">
        <h2>Article 9 - Dispositions diverses</h2>
        
        <section>
            <h3>9.1 Modification</h3>
            <p>Le présent règlement peut être modifié à tout moment. Les participants en sont informés par affichage.</p>
        </section>

        <section>
            <h3>9.2 Application</h3>
            <p>Le présent règlement entre en vigueur dès signature de la convention de formation.</p>
        </section>
    </article>

    <footer class="document-footer">
        <hr>
        <div class="approval-info">
            <p><strong>Version 1.5</strong> - Juillet 2025</p>
            <p class="text-muted"><em>Approuvé par la direction le 15 juillet 2025</em></p>
        </div>
    </footer>
</div>
EOF;
    }

    private function getPrivacyPolicyContent(): string
    {
        return <<<'EOF'
<div class="document-content">
    <h1>POLITIQUE DE CONFIDENTIALITÉ ET PROTECTION DES DONNÉES</h1>

    <section class="document-section">
        <h2>1. Introduction</h2>
        <p><strong>EPROFOS</strong> s'engage à protéger la confidentialité et la sécurité de vos données personnelles conformément au <abbr title="Règlement Général sur la Protection des Données">RGPD</abbr>.</p>
    </section>

    <section class="document-section">
        <h2>2. Responsable du traitement</h2>
        <div class="contact-card">
            <h3>EPROFOS</h3>
            <address>
                123 Rue de la Formation<br>
                75001 Paris<br>
                <strong>Email DPO</strong> : <a href="mailto:dpo@eprofos.fr">dpo@eprofos.fr</a>
            </address>
        </div>
    </section>

    <section class="document-section">
        <h2>3. Données collectées</h2>

        <div class="data-categories">
            <div class="data-category">
                <h3>3.1 Données d'identification</h3>
                <ul>
                    <li>Nom, prénom</li>
                    <li>Date de naissance</li>
                    <li>Adresse postale et email</li>
                    <li>Numéro de téléphone</li>
                </ul>
            </div>

            <div class="data-category">
                <h3>3.2 Données de formation</h3>
                <ul>
                    <li>Parcours professionnel</li>
                    <li>Niveau de qualification</li>
                    <li>Objectifs de formation</li>
                    <li>Résultats d'évaluation</li>
                </ul>
            </div>

            <div class="data-category">
                <h3>3.3 Données techniques</h3>
                <ul>
                    <li>Adresse IP</li>
                    <li>Cookies de fonctionnement</li>
                    <li>Logs de connexion</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="document-section">
        <h2>4. Finalités du traitement</h2>
        <p>Vos données sont utilisées pour :</p>
        <div class="purposes-list">
            <div class="purpose-item">
                <h4>📝 Gestion des inscriptions</h4>
                <p>Traitement de votre dossier de candidature et suivi administratif</p>
            </div>
            <div class="purpose-item">
                <h4>🎓 Suivi pédagogique</h4>
                <p>Accompagnement personnalisé et évaluation des apprentissages</p>
            </div>
            <div class="purpose-item">
                <h4>💰 Facturation et comptabilité</h4>
                <p>Établissement des factures et gestion comptable</p>
            </div>
            <div class="purpose-item">
                <h4>📢 Communication sur nos services</h4>
                <p>Information sur nos formations et services</p>
            </div>
            <div class="purpose-item">
                <h4>⚖️ Respect des obligations légales</h4>
                <p>Conformité aux exigences réglementaires</p>
            </div>
        </div>
    </section>

    <section class="document-section">
        <h2>5. Base légale</h2>
        <p>Le traitement de vos données est fondé sur :</p>
        <ul>
            <li><strong>L'exécution du contrat de formation</strong></li>
            <li><strong>Le respect d'obligations légales</strong></li>
            <li><strong>Votre consentement</strong> (newsletters)</li>
            <li><strong>L'intérêt légitime</strong> (amélioration des services)</li>
        </ul>
    </section>

    <section class="document-section">
        <h2>6. Destinataires des données</h2>
        <p>Vos données peuvent être transmises à :</p>
        <div class="recipients-grid">
            <div class="recipient">
                <h4>👨‍🏫 Nos formateurs</h4>
                <p>Données nécessaires uniquement</p>
            </div>
            <div class="recipient">
                <h4>🔧 Prestataires techniques</h4>
                <p>Hébergement, maintenance</p>
            </div>
            <div class="recipient">
                <h4>💼 Organismes financeurs</h4>
                <p>CPF, OPCO, Pôle emploi</p>
            </div>
            <div class="recipient">
                <h4>⚖️ Autorités légales</h4>
                <p>Si requises par la loi</p>
            </div>
        </div>
    </section>

    <section class="document-section">
        <h2>7. Durée de conservation</h2>
        <div class="retention-table">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Type de données</th>
                        <th>Durée de conservation</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Dossier de formation</td>
                        <td>3 ans après la fin de formation</td>
                    </tr>
                    <tr>
                        <td>Données comptables</td>
                        <td>10 ans</td>
                    </tr>
                    <tr>
                        <td>Cookies</td>
                        <td>13 mois maximum</td>
                    </tr>
                    <tr>
                        <td>Consentement marketing</td>
                        <td>Jusqu'à retrait</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="document-section">
        <h2>8. Vos droits</h2>
        <p>Vous disposez des droits suivants :</p>

        <div class="rights-grid">
            <div class="right-item">
                <h3>👁️ Droit d'accès</h3>
                <p>Obtenir confirmation du traitement et accès à vos données</p>
            </div>
            <div class="right-item">
                <h3>✏️ Droit de rectification</h3>
                <p>Corriger les données inexactes ou incomplètes</p>
            </div>
            <div class="right-item">
                <h3>🗑️ Droit à l'effacement</h3>
                <p>Demander la suppression dans certaines conditions</p>
            </div>
            <div class="right-item">
                <h3>🚫 Droit d'opposition</h3>
                <p>Vous opposer au traitement pour motifs légitimes</p>
            </div>
            <div class="right-item">
                <h3>📦 Droit à la portabilité</h3>
                <p>Récupérer vos données dans un format structuré</p>
            </div>
            <div class="right-item">
                <h3>⏸️ Droit de limitation</h3>
                <p>Demander la limitation du traitement</p>
            </div>
        </div>
    </section>

    <section class="document-section">
        <h2>9. Exercice des droits</h2>
        <div class="contact-rights">
            <p><strong>Pour exercer vos droits :</strong></p>
            <ul>
                <li><i class="fas fa-envelope"></i> <strong>Email</strong> : <a href="mailto:dpo@eprofos.fr">dpo@eprofos.fr</a></li>
                <li><i class="fas fa-mail-bulk"></i> <strong>Courrier</strong> : EPROFOS - DPO, 123 Rue de la Formation, 75001 Paris</li>
            </ul>
            <div class="alert alert-info">
                <p class="mb-0"><i class="fas fa-clock"></i> <strong>Réponse sous 1 mois maximum</strong></p>
            </div>
        </div>
    </section>

    <section class="document-section">
        <h2>10. Sécurité</h2>
        <p>Nous mettons en œuvre des mesures techniques et organisationnelles :</p>
        <div class="security-measures">
            <div class="measure">
                <h4>🔒 Chiffrement des données sensibles</h4>
            </div>
            <div class="measure">
                <h4>🔐 Contrôle d'accès strict</h4>
            </div>
            <div class="measure">
                <h4>💾 Sauvegardes sécurisées</h4>
            </div>
            <div class="measure">
                <h4>📚 Formation du personnel</h4>
            </div>
        </div>
    </section>

    <section class="document-section">
        <h2>11. Cookies</h2>
        
        <div class="cookies-section">
            <h3>11.1 Cookies essentiels</h3>
            <p>Nécessaires au fonctionnement du site (session, sécurité)</p>

            <h3>11.2 Cookies analytiques</h3>
            <p>Mesure d'audience (avec votre consentement)</p>

            <h3>11.3 Gestion des cookies</h3>
            <p>Vous pouvez gérer vos préférences via le panneau de configuration ou les paramètres de votre navigateur.</p>
        </div>
    </section>

    <section class="document-section">
        <h2>12. Réclamation</h2>
        <div class="complaint-info">
            <p>En cas de difficultés, vous pouvez saisir la <strong>CNIL</strong> :</p>
            <ul>
                <li><i class="fas fa-globe"></i> <a href="https://www.cnil.fr" target="_blank">www.cnil.fr</a></li>
                <li><i class="fas fa-map-marker-alt"></i> 3 Place de Fontenoy, 75007 Paris</li>
            </ul>
        </div>
    </section>

    <section class="document-section">
        <h2>13. Modifications</h2>
        <p>Cette politique peut être mise à jour. La version en vigueur est toujours disponible sur notre site web.</p>
    </section>

    <footer class="document-footer">
        <hr>
        <div class="version-info">
            <p><strong>Dernière mise à jour</strong> : 19 juillet 2025</p>
            <p><strong>Version</strong> : 2.0</p>
        </div>
    </footer>
</div>
EOF;
    }

    private function getCourseCatalogContent(): string
    {
        return <<<'EOF'
<div class="document-content">
    <h1>CATALOGUE DE FORMATIONS 2025</h1>

    <section class="document-section">
        <h2>Nos domaines d'expertise</h2>

        <div class="expertise-domains">
            <div class="domain">
                <h3>💻 Développement Web et Mobile</h3>
                <ul>
                    <li><strong>Développeur Web Full Stack</strong> (JavaScript, React, Node.js)</li>
                    <li><strong>Développeur Mobile</strong> (React Native, Flutter)</li>
                    <li><strong>DevOps et Cloud</strong> (AWS, Docker, Kubernetes)</li>
                </ul>
            </div>

            <div class="domain">
                <h3>🎨 Design et UX/UI</h3>
                <ul>
                    <li><strong>Designer UX/UI</strong> (Figma, Adobe Creative Suite)</li>
                    <li><strong>Webdesign Responsive</strong> (HTML5, CSS3, Bootstrap)</li>
                    <li><strong>Motion Design</strong> (After Effects, Blender)</li>
                </ul>
            </div>

            <div class="domain">
                <h3>📊 Data et Intelligence Artificielle</h3>
                <ul>
                    <li><strong>Data Analyst</strong> (Python, SQL, Power BI)</li>
                    <li><strong>Data Scientist</strong> (Machine Learning, Deep Learning)</li>
                    <li><strong>Analyste Business Intelligence</strong> (Tableau, QlikView)</li>
                </ul>
            </div>

            <div class="domain">
                <h3>🌱 Transition Écologique</h3>
                <ul>
                    <li><strong>Consultant RSE</strong> (Reporting ESG, Bilan Carbone)</li>
                    <li><strong>Chargé de Projet Développement Durable</strong></li>
                    <li><strong>Expert en Énergies Renouvelables</strong></li>
                </ul>
            </div>

            <div class="domain">
                <h3>🚀 Entrepreneuriat et Management</h3>
                <ul>
                    <li><strong>Chef de Projet Digital</strong> (Agile, Scrum)</li>
                    <li><strong>Growth Hacker</strong> (Marketing Digital, Analytics)</li>
                    <li><strong>Manager de Transition</strong> (Conduite du changement)</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="document-section">
        <h2>Modalités de formation</h2>

        <div class="training-modalities">
            <div class="modality">
                <h3>🏛️ Présentiel</h3>
                <p>Formations dans nos locaux parisiens équipés des dernières technologies.</p>
            </div>

            <div class="modality">
                <h3>💻 Distanciel</h3>
                <p>Plateforme de formation en ligne interactive avec accompagnement personnalisé.</p>
            </div>

            <div class="modality">
                <h3>🔄 Hybride</h3>
                <p>Alternance entre présentiel et distanciel pour une flexibilité optimale.</p>
            </div>

            <div class="modality">
                <h3>🏢 Intra-entreprise</h3>
                <p>Formations sur mesure dans vos locaux, adaptées à vos besoins spécifiques.</p>
            </div>
        </div>
    </section>

    <section class="document-section">
        <h2>Certifications et financements</h2>

        <div class="certifications-financing">
            <div class="certifications">
                <h3>🏆 Nos certifications</h3>
                <ul>
                    <li><strong>RNCP</strong> (Répertoire National des Certifications Professionnelles)</li>
                    <li><strong>RS</strong> (Répertoire Spécifique)</li>
                    <li><strong>Certifications éditeurs</strong> (Microsoft, Google, AWS)</li>
                </ul>
            </div>

            <div class="financing">
                <h3>💰 Financements possibles</h3>
                <ul>
                    <li><strong>CPF</strong> (Compte Personnel de Formation)</li>
                    <li><strong>OPCO</strong> (Opérateurs de Compétences)</li>
                    <li><strong>Pôle emploi</strong> (AIF, POEI, POEC)</li>
                    <li><strong>Région</strong> (Dispositifs spécifiques)</li>
                    <li><strong>Autofinancement</strong> (facilités de paiement)</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="document-section">
        <h2>Chiffres clés 2024</h2>

        <div class="key-figures">
            <div class="figure-category">
                <h3>👥 Nos apprenants</h3>
                <div class="stats">
                    <div class="stat">
                        <span class="number">1 247</span>
                        <span class="label">apprenants formés</span>
                    </div>
                    <div class="stat">
                        <span class="number">94%</span>
                        <span class="label">de satisfaction</span>
                    </div>
                    <div class="stat">
                        <span class="number">87%</span>
                        <span class="label">de réussite aux certifications</span>
                    </div>
                    <div class="stat">
                        <span class="number">79%</span>
                        <span class="label">de retour à l'emploi à 6 mois</span>
                    </div>
                </div>
            </div>

            <div class="figure-category">
                <h3>🎓 Notre équipe</h3>
                <div class="stats">
                    <div class="stat">
                        <span class="number">45</span>
                        <span class="label">formateurs experts</span>
                    </div>
                    <div class="stat">
                        <span class="number">12</span>
                        <span class="label">années d'expérience moyenne</span>
                    </div>
                    <div class="stat">
                        <span class="number">98%</span>
                        <span class="label">d'avis positifs apprenants</span>
                    </div>
                </div>
            </div>

            <div class="figure-category">
                <h3>🏢 Nos partenaires</h3>
                <div class="stats">
                    <div class="stat">
                        <span class="number">156</span>
                        <span class="label">entreprises partenaires</span>
                    </div>
                    <div class="stat">
                        <span class="number">23</span>
                        <span class="label">OPCO référencés</span>
                    </div>
                    <div class="stat">
                        <span class="number">8</span>
                        <span class="label">régions d'intervention</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="document-section">
        <h2>Services inclus</h2>

        <div class="included-services">
            <div class="service">
                <h3>📚 Ressources pédagogiques</h3>
                <ul>
                    <li>Supports de cours actualisés</li>
                    <li>Plateforme e-learning 24h/24</li>
                    <li>Bibliothèque numérique</li>
                    <li>Veille technologique</li>
                </ul>
            </div>

            <div class="service">
                <h3>🤝 Accompagnement</h3>
                <ul>
                    <li>Entretien de positionnement</li>
                    <li>Suivi pédagogique individualisé</li>
                    <li>Coaching emploi</li>
                    <li>Réseau alumni actif</li>
                </ul>
            </div>

            <div class="service">
                <h3>🔧 Équipements</h3>
                <ul>
                    <li>Ordinateurs dernière génération</li>
                    <li>Logiciels professionnels</li>
                    <li>Espaces collaboratifs</li>
                    <li>Laboratoires techniques</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="document-section">
        <h2>Comment nous rejoindre ?</h2>

        <div class="contact-info-section">
            <div class="contact-methods">
                <h3>📞 Contactez-nous</h3>
                <ul>
                    <li><i class="fas fa-phone"></i> <strong>Téléphone</strong> : <a href="tel:0123456789">01 23 45 67 89</a></li>
                    <li><i class="fas fa-envelope"></i> <strong>Email</strong> : <a href="mailto:info@eprofos.fr">info@eprofos.fr</a></li>
                    <li><i class="fas fa-globe"></i> <strong>Site web</strong> : <a href="https://www.eprofos.fr" target="_blank">www.eprofos.fr</a></li>
                </ul>
            </div>

            <div class="campuses">
                <h3>📍 Nos campus</h3>
                <div class="campus">
                    <h4>Paris Centre</h4>
                    <address>
                        123 Rue de la Formation<br>
                        75001 Paris
                    </address>
                </div>
                <div class="campus">
                    <h4>Boulogne-Billancourt</h4>
                    <address>
                        456 Avenue de l'Innovation<br>
                        92100 Boulogne-Billancourt
                    </address>
                </div>
            </div>

            <div class="info-sessions">
                <h3>📅 Réunions d'information</h3>
                <div class="session-info">
                    <p><strong>Tous les mardis à 18h30</strong></p>
                    <p>En présentiel et en ligne</p>
                    <p><em>Inscription gratuite sur notre site web</em></p>
                </div>
            </div>
        </div>
    </section>

    <footer class="document-footer">
        <hr>
        <div class="catalog-info">
            <p><strong>Catalogue 2025</strong> - Formations de janvier à décembre</p>
            <p class="text-muted"><em>Mis à jour le 19 juillet 2025</em></p>
        </div>
    </footer>
</div>
EOF;
    }

    private function getEvaluationProcedureContent(): string
    {
        return <<<'EOF'
<div class="document-content">
    <h1>PROCÉDURE D'ÉVALUATION DES APPRENTISSAGES</h1>

    <section class="document-section">
        <h2>1. Objectifs de l'évaluation</h2>
        <p>L'évaluation des apprentissages vise à :</p>
        <div class="objectives-list">
            <div class="objective">
                <h4>📏 Mesurer l'atteinte des objectifs pédagogiques</h4>
            </div>
            <div class="objective">
                <h4>🔍 Identifier les acquis et les axes d'amélioration</h4>
            </div>
            <div class="objective">
                <h4>🎯 Adapter le parcours de formation si nécessaire</h4>
            </div>
            <div class="objective">
                <h4>🏆 Préparer à la certification finale</h4>
            </div>
        </div>
    </section>

    <section class="document-section">
        <h2>2. Types d'évaluation</h2>

        <div class="evaluation-types">
            <div class="evaluation-type">
                <h3>2.1 Évaluation diagnostique</h3>
                <div class="type-details">
                    <p><strong>Moment</strong> : Avant le début de formation</p>
                    <p><strong>Objectif</strong> : Positionner le niveau initial</p>
                    <p><strong>Outils</strong> :</p>
                    <ul>
                        <li>Tests de connaissances</li>
                        <li>Entretien individuel</li>
                        <li>Auto-évaluation</li>
                    </ul>
                </div>
            </div>

            <div class="evaluation-type">
                <h3>2.2 Évaluation formative</h3>
                <div class="type-details">
                    <p><strong>Moment</strong> : Pendant la formation</p>
                    <p><strong>Objectif</strong> : Réguler les apprentissages</p>
                    <p><strong>Outils</strong> :</p>
                    <ul>
                        <li>Exercices pratiques</li>
                        <li>Projets intermédiaires</li>
                        <li>Feedback continu</li>
                    </ul>
                </div>
            </div>

            <div class="evaluation-type">
                <h3>2.3 Évaluation sommative</h3>
                <div class="type-details">
                    <p><strong>Moment</strong> : Fin de module/formation</p>
                    <p><strong>Objectif</strong> : Valider les acquis</p>
                    <p><strong>Outils</strong> :</p>
                    <ul>
                        <li>Examens théoriques</li>
                        <li>Projets finaux</li>
                        <li>Soutenances orales</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section class="document-section">
        <h2>3. Modalités d'évaluation</h2>

        <div class="evaluation-modalities">
            <div class="modality">
                <h3>3.1 Évaluation théorique</h3>
                <ul>
                    <li><strong>QCM</strong> : Questions à choix multiples</li>
                    <li><strong>Questions ouvertes</strong> : Rédaction structurée</li>
                    <li><strong>Études de cas</strong> : Analyse de situations</li>
                </ul>
            </div>

            <div class="modality">
                <h3>3.2 Évaluation pratique</h3>
                <ul>
                    <li><strong>Projets individuels</strong> : Réalisation autonome</li>
                    <li><strong>Projets collectifs</strong> : Travail en équipe</li>
                    <li><strong>Mises en situation</strong> : Simulation professionnelle</li>
                </ul>
            </div>

            <div class="modality">
                <h3>3.3 Évaluation continue</h3>
                <ul>
                    <li><strong>Participation</strong> : Engagement en formation</li>
                    <li><strong>Progression</strong> : Évolution des compétences</li>
                    <li><strong>Assiduité</strong> : Présence et ponctualité</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="document-section">
        <h2>4. Critères d'évaluation</h2>

        <div class="evaluation-criteria">
            <div class="criterion-category">
                <h3>4.1 Compétences techniques</h3>
                <ul>
                    <li>Maîtrise des outils et méthodes</li>
                    <li>Application des bonnes pratiques</li>
                    <li>Résolution de problèmes complexes</li>
                </ul>
            </div>

            <div class="criterion-category">
                <h3>4.2 Compétences transversales</h3>
                <ul>
                    <li>Communication orale et écrite</li>
                    <li>Travail en équipe</li>
                    <li>Autonomie et initiative</li>
                    <li>Capacité d'adaptation</li>
                </ul>
            </div>

            <div class="criterion-category">
                <h3>4.3 Compétences comportementales</h3>
                <ul>
                    <li>Respect des délais</li>
                    <li>Qualité relationnelle</li>
                    <li>Éthique professionnelle</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="document-section">
        <h2>5. Barème et notation</h2>

        <div class="grading-section">
            <h3>5.1 Échelle de notation</h3>
            <div class="grading-scale">
                <div class="grade">
                    <span class="grade-letter bg-success">A</span>
                    <span class="grade-range">(18-20)</span>
                    <span class="grade-description">Excellent - Dépasse les attentes</span>
                </div>
                <div class="grade">
                    <span class="grade-letter bg-primary">B</span>
                    <span class="grade-range">(15-17)</span>
                    <span class="grade-description">Très bien - Atteint pleinement les objectifs</span>
                </div>
                <div class="grade">
                    <span class="grade-letter bg-info">C</span>
                    <span class="grade-range">(12-14)</span>
                    <span class="grade-description">Bien - Atteint les objectifs essentiels</span>
                </div>
                <div class="grade">
                    <span class="grade-letter bg-warning">D</span>
                    <span class="grade-range">(10-11)</span>
                    <span class="grade-description">Satisfaisant - Atteint les objectifs minimaux</span>
                </div>
                <div class="grade">
                    <span class="grade-letter bg-danger">E</span>
                    <span class="grade-range">(0-9)</span>
                    <span class="grade-description">Insuffisant - N'atteint pas les objectifs</span>
                </div>
            </div>

            <h3>5.2 Pondération</h3>
            <p>La note finale est calculée selon la répartition :</p>
            <div class="weight-distribution">
                <div class="weight-item">
                    <span class="weight-value">40%</span>
                    <span class="weight-label">Évaluation continue</span>
                </div>
                <div class="weight-item">
                    <span class="weight-value">40%</span>
                    <span class="weight-label">Projets pratiques</span>
                </div>
                <div class="weight-item">
                    <span class="weight-value">20%</span>
                    <span class="weight-label">Examen final</span>
                </div>
            </div>
        </div>
    </section>

    <section class="document-section">
        <h2>6. Remédiations</h2>

        <div class="remediation-section">
            <h3>6.1 Difficultés détectées</h3>
            <p>En cas de difficultés :</p>
            <ul>
                <li>Entretien avec le formateur</li>
                <li>Plan de remédiation personnalisé</li>
                <li>Ressources complémentaires</li>
                <li>Accompagnement renforcé</li>
            </ul>

            <h3>6.2 Rattrapage</h3>
            <p>Possibilité de rattrapage pour :</p>
            <ul>
                <li>Les absences justifiées</li>
                <li>Les notes insuffisantes</li>
                <li>Les compétences non acquises</li>
            </ul>
        </div>
    </section>

    <section class="document-section">
        <h2>7. Communication des résultats</h2>

        <div class="results-communication">
            <h3>7.1 Feedback immédiat</h3>
            <ul>
                <li>Retour oral après chaque évaluation</li>
                <li>Correction collective des exercices</li>
                <li>Conseils d'amélioration personnalisés</li>
            </ul>

            <h3>7.2 Bilan de compétences</h3>
            <p>Document de synthèse comprenant :</p>
            <ul>
                <li>Évaluation détaillée par compétence</li>
                <li>Points forts et axes d'amélioration</li>
                <li>Recommandations pour la suite</li>
            </ul>

            <h3>7.3 Attestation de formation</h3>
            <p>Délivrée en fin de parcours avec :</p>
            <ul>
                <li>Objectifs atteints</li>
                <li>Compétences acquises</li>
                <li>Niveau de maîtrise</li>
                <li>Durée de formation</li>
            </ul>
        </div>
    </section>

    <section class="document-section">
        <h2>8. Recours et contestations</h2>

        <div class="appeals-section">
            <h3>8.1 Procédure de recours</h3>
            <p>En cas de désaccord :</p>
            <ol>
                <li>Discussion avec le formateur</li>
                <li>Recours auprès du responsable pédagogique</li>
                <li>Médiation si nécessaire</li>
            </ol>

            <h3>8.2 Commission d'évaluation</h3>
            <p>Composée de :</p>
            <ul>
                <li>Responsable pédagogique</li>
                <li>Formateur concerné</li>
                <li>Formateur externe</li>
                <li>Représentant des apprenants</li>
            </ul>
        </div>
    </section>

    <section class="document-section">
        <h2>9. Amélioration continue</h2>

        <div class="improvement-section">
            <h3>9.1 Évaluation de l'évaluation</h3>
            <ul>
                <li>Questionnaire de satisfaction</li>
                <li>Retour des formateurs</li>
                <li>Analyse des résultats</li>
                <li>Ajustements méthodologiques</li>
            </ul>

            <h3>9.2 Formation des évaluateurs</h3>
            <ul>
                <li>Formation à l'évaluation des compétences</li>
                <li>Harmonisation des pratiques</li>
                <li>Mise à jour des grilles d'évaluation</li>
            </ul>
        </div>
    </section>

    <footer class="document-footer">
        <hr>
        <div class="procedure-info">
            <p><em>Procédure validée par l'équipe pédagogique</em></p>
            <p><strong>Version 1.2</strong> - Juillet 2025</p>
        </div>
    </footer>
</div>
EOF;
    }
}
