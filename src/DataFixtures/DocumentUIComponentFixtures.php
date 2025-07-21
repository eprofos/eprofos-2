<?php

namespace App\DataFixtures;

use App\Entity\Document\DocumentUIComponent;
use App\Entity\Document\DocumentUITemplate;
use App\Entity\User\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Document UI Component Fixtures - Creates UI components for templates
 * 
 * Provides various pre-configured components for document templates
 * including headers, footers, content sections, images, tables, etc.
 */
class DocumentUIComponentFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $this->createStandardTemplateComponents($manager);
        $this->createLetterheadTemplateComponents($manager);
        $this->createCertificateTemplateComponents($manager);
        $this->createLegalTemplateComponents($manager);
        $this->createHandbookTemplateComponents($manager);
        $this->createQualityTemplateComponents($manager);
        $this->createReportTemplateComponents($manager);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            DocumentUITemplateFixtures::class,
            UserFixtures::class,
        ];
    }

    /**
     * Create components for Standard template
     */
    private function createStandardTemplateComponents(ObjectManager $manager): void
    {
        $template = $this->getReference(DocumentUITemplateFixtures::STANDARD_TEMPLATE_REFERENCE, DocumentUITemplate::class);
        $user = $this->getReference(UserFixtures::ADMIN_USER_REFERENCE, User::class);

        $components = [
            [
                'name' => 'Logo En-tête',
                'type' => DocumentUIComponent::TYPE_LOGO,
                'zone' => DocumentUITemplate::ZONE_HEADER,
                'content' => '/images/logo-eprofos.png',
                'styleConfig' => [
                    'max-height' => '40px',
                    'max-width' => '120px',
                    'float' => 'left'
                ],
                'positionConfig' => [
                    'position' => 'absolute',
                    'top' => '5mm',
                    'left' => '0'
                ],
                'dataBinding' => [
                    'src' => 'company.logo',
                    'alt' => 'company.name'
                ],
                'sortOrder' => 1,
                'isRequired' => true
            ],
            [
                'name' => 'Nom Entreprise',
                'type' => DocumentUIComponent::TYPE_TEXT,
                'zone' => DocumentUITemplate::ZONE_HEADER,
                'content' => '{{company_name}}',
                'styleConfig' => [
                    'font-size' => '18pt',
                    'font-weight' => 'bold',
                    'color' => '#007bff',
                    'text-align' => 'center'
                ],
                'dataBinding' => [
                    'text' => 'company.name'
                ],
                'sortOrder' => 2,
                'isRequired' => true
            ],
            [
                'name' => 'Date Document',
                'type' => DocumentUIComponent::TYPE_DATE,
                'zone' => DocumentUITemplate::ZONE_HEADER,
                'content' => '{{date}}',
                'styleConfig' => [
                    'font-size' => '10pt',
                    'color' => '#666',
                    'text-align' => 'right'
                ],
                'positionConfig' => [
                    'float' => 'right'
                ],
                'sortOrder' => 3,
                'isRequired' => false
            ],
            [
                'name' => 'Titre Document',
                'type' => DocumentUIComponent::TYPE_TEXT,
                'zone' => DocumentUITemplate::ZONE_BODY,
                'content' => '{{title}}',
                'styleConfig' => [
                    'font-size' => '16pt',
                    'font-weight' => 'bold',
                    'color' => '#007bff',
                    'text-align' => 'center',
                    'margin-bottom' => '15mm'
                ],
                'dataBinding' => [
                    'text' => 'document.title'
                ],
                'sortOrder' => 1,
                'isRequired' => true
            ],
            [
                'name' => 'Contenu Principal',
                'type' => DocumentUIComponent::TYPE_TEXT,
                'zone' => DocumentUITemplate::ZONE_BODY,
                'content' => '{{content}}',
                'styleConfig' => [
                    'text-align' => 'justify',
                    'line-height' => '1.4'
                ],
                'dataBinding' => [
                    'text' => 'document.content'
                ],
                'sortOrder' => 2,
                'isRequired' => true
            ],
            [
                'name' => 'Informations Contact',
                'type' => DocumentUIComponent::TYPE_TEXT,
                'zone' => DocumentUITemplate::ZONE_FOOTER,
                'content' => '{{company_address}} | {{company_phone}} | {{company_email}}',
                'styleConfig' => [
                    'font-size' => '9pt',
                    'color' => '#666',
                    'text-align' => 'left'
                ],
                'dataBinding' => [
                    'address' => 'company.address',
                    'phone' => 'company.phone',
                    'email' => 'company.email'
                ],
                'sortOrder' => 1,
                'isRequired' => false
            ],
            [
                'name' => 'Numéro de Page',
                'type' => DocumentUIComponent::TYPE_PAGE_NUMBER,
                'zone' => DocumentUITemplate::ZONE_FOOTER,
                'content' => 'Page {{page_number}} sur {{total_pages}}',
                'styleConfig' => [
                    'font-size' => '9pt',
                    'color' => '#666',
                    'text-align' => 'right'
                ],
                'positionConfig' => [
                    'float' => 'right'
                ],
                'sortOrder' => 2,
                'isRequired' => false
            ]
        ];

        $this->createComponentsForTemplate($manager, $template, $user, $components);
    }

    /**
     * Create components for Letterhead template
     */
    private function createLetterheadTemplateComponents(ObjectManager $manager): void
    {
        $template = $this->getReference(DocumentUITemplateFixtures::LETTERHEAD_TEMPLATE_REFERENCE, DocumentUITemplate::class);
        $user = $this->getReference(UserFixtures::ADMIN_USER_REFERENCE, User::class);

        $components = [
            [
                'name' => 'Logo Officiel',
                'type' => DocumentUIComponent::TYPE_LOGO,
                'zone' => DocumentUITemplate::ZONE_HEADER,
                'content' => '/images/logo-official.png',
                'styleConfig' => [
                    'max-height' => '60px',
                    'max-width' => '100px'
                ],
                'dataBinding' => [
                    'src' => 'company.official_logo'
                ],
                'sortOrder' => 1,
                'isRequired' => true
            ],
            [
                'name' => 'En-tête Entreprise',
                'type' => DocumentUIComponent::TYPE_TEXT,
                'zone' => DocumentUITemplate::ZONE_HEADER,
                'htmlContent' => '<div class="company-section">
                    <h1 class="company-name">{{company_name}}</h1>
                    <p class="company-tagline">{{company_tagline}}</p>
                    <div class="contact-info">
                        <p>{{company_address}}</p>
                        <p>{{company_phone}} | {{company_email}}</p>
                    </div>
                </div>',
                'styleConfig' => [
                    'text-align' => 'right',
                    'flex' => '1',
                    'margin-left' => '20mm'
                ],
                'sortOrder' => 2,
                'isRequired' => true
            ],
            [
                'name' => 'Date Courrier',
                'type' => DocumentUIComponent::TYPE_DATE,
                'zone' => DocumentUITemplate::ZONE_BODY,
                'content' => '{{date}}',
                'styleConfig' => [
                    'text-align' => 'right',
                    'font-size' => '11pt',
                    'margin-bottom' => '10mm'
                ],
                'sortOrder' => 1,
                'isRequired' => true
            ],
            [
                'name' => 'Objet',
                'type' => DocumentUIComponent::TYPE_TEXT,
                'zone' => DocumentUITemplate::ZONE_BODY,
                'content' => '<strong>Objet:</strong> {{title}}',
                'styleConfig' => [
                    'margin-bottom' => '8mm',
                    'font-size' => '12pt'
                ],
                'conditionalDisplay' => [
                    ['field' => 'document.title', 'operator' => 'not_empty', 'value' => '']
                ],
                'sortOrder' => 2,
                'isRequired' => false
            ],
            [
                'name' => 'Corps de Lettre',
                'type' => DocumentUIComponent::TYPE_TEXT,
                'zone' => DocumentUITemplate::ZONE_BODY,
                'content' => '{{content}}',
                'styleConfig' => [
                    'text-align' => 'justify',
                    'text-indent' => '8mm',
                    'line-height' => '1.5'
                ],
                'sortOrder' => 3,
                'isRequired' => true
            ],
            [
                'name' => 'Signature',
                'type' => DocumentUIComponent::TYPE_SIGNATURE,
                'zone' => DocumentUITemplate::ZONE_BODY,
                'content' => 'Cordialement,',
                'styleConfig' => [
                    'margin-top' => '15mm',
                    'text-align' => 'right'
                ],
                'dataBinding' => [
                    'name' => 'signature.name',
                    'title' => 'signature.title',
                    'image' => 'signature.image'
                ],
                'sortOrder' => 4,
                'isRequired' => false
            ],
            [
                'name' => 'Mentions Légales',
                'type' => DocumentUIComponent::TYPE_TEXT,
                'zone' => DocumentUITemplate::ZONE_FOOTER,
                'content' => '{{legal_info}} | {{registration_number}}',
                'styleConfig' => [
                    'font-size' => '9pt',
                    'color' => '#666',
                    'text-align' => 'center'
                ],
                'sortOrder' => 1,
                'isRequired' => true
            ]
        ];

        $this->createComponentsForTemplate($manager, $template, $user, $components);
    }

    /**
     * Create components for Certificate template
     */
    private function createCertificateTemplateComponents(ObjectManager $manager): void
    {
        $template = $this->getReference(DocumentUITemplateFixtures::CERTIFICATE_TEMPLATE_REFERENCE, DocumentUITemplate::class);
        $user = $this->getReference(UserFixtures::ADMIN_USER_REFERENCE, User::class);

        $components = [
            [
                'name' => 'Bordure Décorative',
                'type' => DocumentUIComponent::TYPE_CUSTOM_HTML,
                'zone' => DocumentUITemplate::ZONE_HEADER,
                'htmlContent' => '<div class="certificate-border"></div>',
                'styleConfig' => [
                    'border' => '8px solid #ffc107',
                    'border-radius' => '15px',
                    'position' => 'absolute',
                    'top' => '0',
                    'left' => '0',
                    'right' => '0',
                    'bottom' => '0'
                ],
                'sortOrder' => 1,
                'isRequired' => true
            ],
            [
                'name' => 'Logo Certificat',
                'type' => DocumentUIComponent::TYPE_LOGO,
                'zone' => DocumentUITemplate::ZONE_HEADER,
                'content' => '/images/logo-certificate.png',
                'styleConfig' => [
                    'max-height' => '50px',
                    'margin-bottom' => '10mm',
                    'display' => 'block',
                    'margin-left' => 'auto',
                    'margin-right' => 'auto'
                ],
                'sortOrder' => 2,
                'isRequired' => false
            ],
            [
                'name' => 'Titre Certificat',
                'type' => DocumentUIComponent::TYPE_TEXT,
                'zone' => DocumentUITemplate::ZONE_HEADER,
                'content' => '{{certificate_title}}',
                'styleConfig' => [
                    'font-size' => '28pt',
                    'font-weight' => 'bold',
                    'color' => '#ffc107',
                    'text-align' => 'center',
                    'text-shadow' => '1px 1px 2px rgba(0,0,0,0.1)'
                ],
                'dataBinding' => [
                    'text' => 'certificate.title'
                ],
                'sortOrder' => 3,
                'isRequired' => true
            ],
            [
                'name' => 'Texte Certification',
                'type' => DocumentUIComponent::TYPE_TEXT,
                'zone' => DocumentUITemplate::ZONE_BODY,
                'content' => 'Nous certifions par la présente que',
                'styleConfig' => [
                    'font-size' => '14pt',
                    'color' => '#333',
                    'text-align' => 'center',
                    'margin' => '5mm 0'
                ],
                'sortOrder' => 1,
                'isRequired' => true
            ],
            [
                'name' => 'Nom Bénéficiaire',
                'type' => DocumentUIComponent::TYPE_TEXT,
                'zone' => DocumentUITemplate::ZONE_BODY,
                'content' => '{{recipient_name}}',
                'styleConfig' => [
                    'font-size' => '24pt',
                    'font-weight' => 'bold',
                    'color' => '#007bff',
                    'text-align' => 'center',
                    'margin' => '10mm 0',
                    'text-decoration' => 'underline',
                    'text-decoration-color' => '#ffc107'
                ],
                'dataBinding' => [
                    'text' => 'recipient.name'
                ],
                'sortOrder' => 2,
                'isRequired' => true
            ],
            [
                'name' => 'Texte Achèvement',
                'type' => DocumentUIComponent::TYPE_TEXT,
                'zone' => DocumentUITemplate::ZONE_BODY,
                'content' => 'a suivi avec succès la formation',
                'styleConfig' => [
                    'font-size' => '14pt',
                    'color' => '#333',
                    'text-align' => 'center',
                    'margin' => '5mm 0'
                ],
                'sortOrder' => 3,
                'isRequired' => true
            ],
            [
                'name' => 'Titre Formation',
                'type' => DocumentUIComponent::TYPE_TEXT,
                'zone' => DocumentUITemplate::ZONE_BODY,
                'content' => '{{formation_title}}',
                'styleConfig' => [
                    'font-size' => '18pt',
                    'font-weight' => 'bold',
                    'color' => '#dc3545',
                    'text-align' => 'center',
                    'margin' => '8mm 0',
                    'font-style' => 'italic'
                ],
                'dataBinding' => [
                    'text' => 'formation.title'
                ],
                'sortOrder' => 4,
                'isRequired' => true
            ],
            [
                'name' => 'Détails Formation',
                'type' => DocumentUIComponent::TYPE_TEXT,
                'zone' => DocumentUITemplate::ZONE_BODY,
                'htmlContent' => '<div class="formation-details">
                    <p>d\'une durée de <strong>{{duration}}</strong></p>
                    <p>achevée le <strong>{{completion_date}}</strong></p>
                </div>',
                'styleConfig' => [
                    'font-size' => '12pt',
                    'color' => '#666',
                    'text-align' => 'center',
                    'margin' => '8mm 0'
                ],
                'dataBinding' => [
                    'duration' => 'formation.duration',
                    'completion_date' => 'completion.date'
                ],
                'sortOrder' => 5,
                'isRequired' => true
            ],
            [
                'name' => 'Signature Responsable',
                'type' => DocumentUIComponent::TYPE_SIGNATURE,
                'zone' => DocumentUITemplate::ZONE_FOOTER,
                'content' => 'Signature du responsable pédagogique',
                'styleConfig' => [
                    'text-align' => 'center',
                    'position' => 'absolute',
                    'bottom' => '15mm',
                    'left' => '20mm'
                ],
                'dataBinding' => [
                    'name' => 'signature.name',
                    'image' => 'signature.image'
                ],
                'sortOrder' => 1,
                'isRequired' => true
            ],
            [
                'name' => 'Date Délivrance',
                'type' => DocumentUIComponent::TYPE_DATE,
                'zone' => DocumentUITemplate::ZONE_FOOTER,
                'content' => 'Délivré le {{date}}',
                'styleConfig' => [
                    'font-size' => '10pt',
                    'color' => '#666',
                    'position' => 'absolute',
                    'bottom' => '15mm',
                    'right' => '20mm'
                ],
                'sortOrder' => 2,
                'isRequired' => true
            ]
        ];

        $this->createComponentsForTemplate($manager, $template, $user, $components);
    }

    /**
     * Create components for Legal template
     */
    private function createLegalTemplateComponents(ObjectManager $manager): void
    {
        $template = $this->getReference(DocumentUITemplateFixtures::LEGAL_TEMPLATE_REFERENCE, DocumentUITemplate::class);
        $user = $this->getReference(UserFixtures::ADMIN_USER_REFERENCE, User::class);

        $components = [
            [
                'name' => 'Références Document',
                'type' => DocumentUIComponent::TYPE_TEXT,
                'zone' => DocumentUITemplate::ZONE_HEADER,
                'htmlContent' => '<div class="document-reference">
                    <span>Réf: {{document_reference}}</span>
                    <span>{{legal_reference}}</span>
                </div>',
                'styleConfig' => [
                    'font-size' => '9pt',
                    'color' => '#666'
                ],
                'dataBinding' => [
                    'document_reference' => 'document.reference',
                    'legal_reference' => 'document.legal_reference'
                ],
                'sortOrder' => 1,
                'isRequired' => true
            ],
            [
                'name' => 'Dates Légales',
                'type' => DocumentUIComponent::TYPE_TEXT,
                'zone' => DocumentUITemplate::ZONE_HEADER,
                'htmlContent' => '<div class="dates">
                    <span>En vigueur: {{effective_date}}</span>
                    <span>Révision: {{review_date}}</span>
                </div>',
                'styleConfig' => [
                    'font-size' => '9pt',
                    'color' => '#666',
                    'text-align' => 'right'
                ],
                'dataBinding' => [
                    'effective_date' => 'document.effective_date',
                    'review_date' => 'document.review_date'
                ],
                'sortOrder' => 2,
                'isRequired' => true
            ],
            [
                'name' => 'Titre Légal',
                'type' => DocumentUIComponent::TYPE_TEXT,
                'zone' => DocumentUITemplate::ZONE_BODY,
                'content' => '{{title}}',
                'styleConfig' => [
                    'font-size' => '18pt',
                    'font-weight' => 'bold',
                    'color' => '#dc3545',
                    'text-align' => 'center',
                    'margin' => '10mm 0',
                    'text-transform' => 'uppercase'
                ],
                'sortOrder' => 1,
                'isRequired' => true
            ],
            [
                'name' => 'Table des Matières',
                'type' => DocumentUIComponent::TYPE_LIST,
                'zone' => DocumentUITemplate::ZONE_BODY,
                'content' => '{{table_of_contents}}',
                'styleConfig' => [
                    'margin' => '10mm 0',
                    'padding-left' => '5mm'
                ],
                'dataBinding' => [
                    'list_data' => 'document.table_of_contents'
                ],
                'conditionalDisplay' => [
                    ['field' => 'document.table_of_contents', 'operator' => 'not_empty', 'value' => '']
                ],
                'sortOrder' => 2,
                'isRequired' => false
            ],
            [
                'name' => 'Contenu Légal',
                'type' => DocumentUIComponent::TYPE_TEXT,
                'zone' => DocumentUITemplate::ZONE_BODY,
                'content' => '{{content}}',
                'styleConfig' => [
                    'text-align' => 'justify',
                    'line-height' => '1.6'
                ],
                'sortOrder' => 3,
                'isRequired' => true
            ],
            [
                'name' => 'Mention Confidentialité',
                'type' => DocumentUIComponent::TYPE_TEXT,
                'zone' => DocumentUITemplate::ZONE_FOOTER,
                'content' => '{{company_name}} - Document confidentiel',
                'styleConfig' => [
                    'font-size' => '9pt',
                    'color' => '#666'
                ],
                'sortOrder' => 1,
                'isRequired' => true
            ],
            [
                'name' => 'Pagination Légale',
                'type' => DocumentUIComponent::TYPE_PAGE_NUMBER,
                'zone' => DocumentUITemplate::ZONE_FOOTER,
                'content' => 'Page {{page_number}} sur {{total_pages}}',
                'styleConfig' => [
                    'font-size' => '9pt',
                    'color' => '#666',
                    'text-align' => 'right'
                ],
                'sortOrder' => 2,
                'isRequired' => true
            ]
        ];

        $this->createComponentsForTemplate($manager, $template, $user, $components);
    }

    /**
     * Create components for Handbook template
     */
    private function createHandbookTemplateComponents(ObjectManager $manager): void
    {
        $template = $this->getReference(DocumentUITemplateFixtures::HANDBOOK_TEMPLATE_REFERENCE, DocumentUITemplate::class);
        $user = $this->getReference(UserFixtures::ADMIN_USER_REFERENCE, User::class);

        $components = [
            [
                'name' => 'Bannière Bienvenue',
                'type' => DocumentUIComponent::TYPE_CUSTOM_HTML,
                'zone' => DocumentUITemplate::ZONE_HEADER,
                'htmlContent' => '<div class="welcome-banner">
                    <h1>{{welcome_message}}</h1>
                    <p class="academic-year">Année {{academic_year}}</p>
                </div>',
                'styleConfig' => [
                    'background' => 'linear-gradient(135deg, #007bff, #0056b3)',
                    'color' => 'white',
                    'padding' => '10mm',
                    'border-radius' => '10px',
                    'text-align' => 'center'
                ],
                'dataBinding' => [
                    'welcome_message' => 'welcome.message',
                    'academic_year' => 'academic.year'
                ],
                'sortOrder' => 1,
                'isRequired' => true
            ],
            [
                'name' => 'Informations Étudiant',
                'type' => DocumentUIComponent::TYPE_CUSTOM_HTML,
                'zone' => DocumentUITemplate::ZONE_BODY,
                'htmlContent' => '<div class="student-info">
                    <h2>Informations personnelles</h2>
                    <p><strong>Nom:</strong> {{student_name}}</p>
                    <p><strong>Programme:</strong> {{program_name}}</p>
                </div>',
                'styleConfig' => [
                    'background' => '#f8f9fa',
                    'border' => '1px solid #dee2e6',
                    'border-radius' => '5px',
                    'padding' => '8mm',
                    'margin-bottom' => '10mm'
                ],
                'dataBinding' => [
                    'student_name' => 'student.name',
                    'program_name' => 'program.name'
                ],
                'sortOrder' => 1,
                'isRequired' => false
            ],
            [
                'name' => 'Table des Matières Livret',
                'type' => DocumentUIComponent::TYPE_LIST,
                'zone' => DocumentUITemplate::ZONE_BODY,
                'content' => '1. Présentation d\'EPROFOS\n2. Votre parcours de formation\n3. Règlement intérieur\n4. Services disponibles\n5. Contacts utiles',
                'styleConfig' => [
                    'list-style-type' => 'decimal',
                    'margin' => '8mm 0',
                    'padding-left' => '8mm'
                ],
                'sortOrder' => 2,
                'isRequired' => true
            ],
            [
                'name' => 'Contenu Livret',
                'type' => DocumentUIComponent::TYPE_TEXT,
                'zone' => DocumentUITemplate::ZONE_BODY,
                'content' => '{{content}}',
                'styleConfig' => [
                    'text-align' => 'justify',
                    'line-height' => '1.5'
                ],
                'sortOrder' => 3,
                'isRequired' => true
            ],
            [
                'name' => 'Encadré Information',
                'type' => DocumentUIComponent::TYPE_CUSTOM_HTML,
                'zone' => DocumentUITemplate::ZONE_BODY,
                'htmlContent' => '<div class="info-box">
                    <h3>💡 Conseil</h3>
                    <p>N\'hésitez pas à consulter ce livret régulièrement et à contacter notre équipe pour toute question.</p>
                </div>',
                'styleConfig' => [
                    'background' => '#e3f2fd',
                    'border-left' => '4px solid #2196f3',
                    'padding' => '5mm',
                    'margin' => '5mm 0'
                ],
                'sortOrder' => 4,
                'isRequired' => false
            ],
            [
                'name' => 'Contact Livret',
                'type' => DocumentUIComponent::TYPE_TEXT,
                'zone' => DocumentUITemplate::ZONE_FOOTER,
                'content' => '{{company_name}} | {{company_phone}} | {{company_email}}',
                'styleConfig' => [
                    'font-size' => '9pt',
                    'color' => '#666'
                ],
                'sortOrder' => 1,
                'isRequired' => true
            ],
            [
                'name' => 'Page Livret',
                'type' => DocumentUIComponent::TYPE_PAGE_NUMBER,
                'zone' => DocumentUITemplate::ZONE_FOOTER,
                'content' => 'Page {{page_number}}',
                'styleConfig' => [
                    'font-size' => '9pt',
                    'color' => '#666',
                    'text-align' => 'right'
                ],
                'sortOrder' => 2,
                'isRequired' => true
            ]
        ];

        $this->createComponentsForTemplate($manager, $template, $user, $components);
    }

    /**
     * Create components for Quality template
     */
    private function createQualityTemplateComponents(ObjectManager $manager): void
    {
        $template = $this->getReference(DocumentUITemplateFixtures::QUALITY_TEMPLATE_REFERENCE, DocumentUITemplate::class);
        $user = $this->getReference(UserFixtures::ADMIN_USER_REFERENCE, User::class);

        $components = [
            [
                'name' => 'Badge Qualiopi',
                'type' => DocumentUIComponent::TYPE_CUSTOM_HTML,
                'zone' => DocumentUITemplate::ZONE_HEADER,
                'htmlContent' => '<div class="qualiopi-badge">
                    <span class="badge-text">QUALIOPI</span>
                    <span class="criterion">{{qualiopi_criterion}}</span>
                </div>',
                'styleConfig' => [
                    'background' => '#ffc107',
                    'color' => '#000',
                    'padding' => '8mm',
                    'border-radius' => '10px',
                    'text-align' => 'center',
                    'min-width' => '40mm'
                ],
                'dataBinding' => [
                    'qualiopi_criterion' => 'qualiopi.criterion'
                ],
                'sortOrder' => 1,
                'isRequired' => true
            ],
            [
                'name' => 'Informations Document Qualité',
                'type' => DocumentUIComponent::TYPE_CUSTOM_HTML,
                'zone' => DocumentUITemplate::ZONE_HEADER,
                'htmlContent' => '<div class="document-info">
                    <h1 class="document-title">{{title}}</h1>
                    <div class="version-info">
                        <span>Version {{version_number}}</span>
                        <span>{{date}}</span>
                    </div>
                </div>',
                'styleConfig' => [
                    'flex' => '1',
                    'margin-left' => '15mm'
                ],
                'dataBinding' => [
                    'version_number' => 'document.version',
                    'title' => 'document.title'
                ],
                'sortOrder' => 2,
                'isRequired' => true
            ],
            [
                'name' => 'Informations Audit',
                'type' => DocumentUIComponent::TYPE_CUSTOM_HTML,
                'zone' => DocumentUITemplate::ZONE_BODY,
                'htmlContent' => '<div class="audit-info">
                    <h2>Informations audit</h2>
                    <p><strong>Date d\'audit:</strong> {{audit_date}}</p>
                    <p><strong>Responsable qualité:</strong> {{responsible_person}}</p>
                </div>',
                'styleConfig' => [
                    'background' => '#fff8dc',
                    'border' => '1px solid #ffc107',
                    'border-radius' => '5px',
                    'padding' => '8mm',
                    'margin-bottom' => '10mm'
                ],
                'dataBinding' => [
                    'audit_date' => 'audit.date',
                    'responsible_person' => 'responsible.person'
                ],
                'sortOrder' => 1,
                'isRequired' => true
            ],
            [
                'name' => 'Indicateur Qualité',
                'type' => DocumentUIComponent::TYPE_CUSTOM_HTML,
                'zone' => DocumentUITemplate::ZONE_BODY,
                'htmlContent' => '<div class="quality-indicator">
                    <h3>📊 Indicateur de performance</h3>
                    <p>Ce document contribue à l\'amélioration continue de la qualité de nos formations dans le cadre de la certification Qualiopi.</p>
                </div>',
                'styleConfig' => [
                    'background' => '#f0f8ff',
                    'border-left' => '4px solid #007bff',
                    'padding' => '5mm',
                    'margin' => '5mm 0'
                ],
                'sortOrder' => 2,
                'isRequired' => false
            ],
            [
                'name' => 'Contenu Qualité',
                'type' => DocumentUIComponent::TYPE_TEXT,
                'zone' => DocumentUITemplate::ZONE_BODY,
                'content' => '{{content}}',
                'styleConfig' => [
                    'text-align' => 'justify',
                    'line-height' => '1.5'
                ],
                'sortOrder' => 3,
                'isRequired' => true
            ],
            [
                'name' => 'Note Conformité',
                'type' => DocumentUIComponent::TYPE_CUSTOM_HTML,
                'zone' => DocumentUITemplate::ZONE_BODY,
                'htmlContent' => '<div class="compliance-note">
                    <p>✅ <strong>Conformité Qualiopi :</strong> Ce document respecte les exigences du référentiel national qualité.</p>
                </div>',
                'styleConfig' => [
                    'background' => '#f0fff0',
                    'border' => '1px solid #28a745',
                    'border-radius' => '3px',
                    'padding' => '5mm',
                    'margin' => '5mm 0',
                    'font-size' => '10pt'
                ],
                'sortOrder' => 4,
                'isRequired' => false
            ],
            [
                'name' => 'Footer Qualité',
                'type' => DocumentUIComponent::TYPE_TEXT,
                'zone' => DocumentUITemplate::ZONE_FOOTER,
                'content' => 'Document qualité certifié Qualiopi',
                'styleConfig' => [
                    'font-size' => '9pt',
                    'color' => '#666'
                ],
                'sortOrder' => 1,
                'isRequired' => true
            ],
            [
                'name' => 'Pagination Qualité',
                'type' => DocumentUIComponent::TYPE_PAGE_NUMBER,
                'zone' => DocumentUITemplate::ZONE_FOOTER,
                'content' => 'Page {{page_number}} sur {{total_pages}}',
                'styleConfig' => [
                    'font-size' => '9pt',
                    'color' => '#666',
                    'text-align' => 'right'
                ],
                'sortOrder' => 2,
                'isRequired' => true
            ]
        ];

        $this->createComponentsForTemplate($manager, $template, $user, $components);
    }

    /**
     * Create components for Report template
     */
    private function createReportTemplateComponents(ObjectManager $manager): void
    {
        $template = $this->getReference(DocumentUITemplateFixtures::REPORT_TEMPLATE_REFERENCE, DocumentUITemplate::class);
        $user = $this->getReference(UserFixtures::ADMIN_USER_REFERENCE, User::class);

        $components = [
            [
                'name' => 'En-tête Rapport',
                'type' => DocumentUIComponent::TYPE_CUSTOM_HTML,
                'zone' => DocumentUITemplate::ZONE_HEADER,
                'htmlContent' => '<div class="report-info">
                    <h1 class="report-title">{{report_title}}</h1>
                    <p class="report-period">{{report_period}}</p>
                    <div class="author-info">
                        <span>Auteur: {{author_name}}</span>
                        <span>Département: {{department}}</span>
                    </div>
                </div>',
                'dataBinding' => [
                    'report_title' => 'report.title',
                    'report_period' => 'report.period',
                    'author_name' => 'author.name',
                    'department' => 'author.department'
                ],
                'sortOrder' => 1,
                'isRequired' => true
            ],
            [
                'name' => 'Date Rapport',
                'type' => DocumentUIComponent::TYPE_DATE,
                'zone' => DocumentUITemplate::ZONE_HEADER,
                'content' => '{{date}}',
                'styleConfig' => [
                    'font-size' => '10pt',
                    'color' => '#666',
                    'text-align' => 'right'
                ],
                'sortOrder' => 2,
                'isRequired' => true
            ],
            [
                'name' => 'Résumé Exécutif',
                'type' => DocumentUIComponent::TYPE_CUSTOM_HTML,
                'zone' => DocumentUITemplate::ZONE_BODY,
                'htmlContent' => '<div class="summary-box">
                    <h2>📋 Résumé exécutif</h2>
                    <p>{{executive_summary}}</p>
                </div>',
                'styleConfig' => [
                    'background' => '#f8f9fa',
                    'border' => '1px solid #dee2e6',
                    'border-radius' => '5px',
                    'padding' => '8mm',
                    'margin' => '8mm 0'
                ],
                'dataBinding' => [
                    'executive_summary' => 'report.executive_summary'
                ],
                'conditionalDisplay' => [
                    ['field' => 'report.executive_summary', 'operator' => 'not_empty', 'value' => '']
                ],
                'sortOrder' => 1,
                'isRequired' => false
            ],
            [
                'name' => 'Indicateurs Clés',
                'type' => DocumentUIComponent::TYPE_CUSTOM_HTML,
                'zone' => DocumentUITemplate::ZONE_BODY,
                'htmlContent' => '<div class="kpi-box">
                    <h3>📊 Indicateurs clés de performance</h3>
                    <ul>
                        <li>Objectif principal : {{main_objective}}</li>
                        <li>Résultat obtenu : {{result_achieved}}</li>
                        <li>Taux de réussite : {{success_rate}}</li>
                    </ul>
                </div>',
                'styleConfig' => [
                    'background' => '#e8f5e8',
                    'border-left' => '4px solid #28a745',
                    'padding' => '5mm',
                    'margin' => '5mm 0'
                ],
                'dataBinding' => [
                    'main_objective' => 'kpi.main_objective',
                    'result_achieved' => 'kpi.result_achieved',
                    'success_rate' => 'kpi.success_rate'
                ],
                'conditionalDisplay' => [
                    ['field' => 'kpi', 'operator' => 'not_empty', 'value' => '']
                ],
                'sortOrder' => 2,
                'isRequired' => false
            ],
            [
                'name' => 'Graphique Placeholder',
                'type' => DocumentUIComponent::TYPE_CUSTOM_HTML,
                'zone' => DocumentUITemplate::ZONE_BODY,
                'htmlContent' => '<div class="chart-placeholder">
                    📈 Espace réservé pour graphique ou tableau
                </div>',
                'styleConfig' => [
                    'background' => '#f8f9fa',
                    'border' => '2px dashed #dee2e6',
                    'height' => '80mm',
                    'display' => 'flex',
                    'align-items' => 'center',
                    'justify-content' => 'center',
                    'color' => '#666',
                    'font-style' => 'italic',
                    'margin' => '8mm 0'
                ],
                'sortOrder' => 3,
                'isRequired' => false
            ],
            [
                'name' => 'Contenu Rapport',
                'type' => DocumentUIComponent::TYPE_TEXT,
                'zone' => DocumentUITemplate::ZONE_BODY,
                'content' => '{{content}}',
                'styleConfig' => [
                    'text-align' => 'justify',
                    'line-height' => '1.4'
                ],
                'sortOrder' => 4,
                'isRequired' => true
            ],
            [
                'name' => 'Footer Confidentiel',
                'type' => DocumentUIComponent::TYPE_TEXT,
                'zone' => DocumentUITemplate::ZONE_FOOTER,
                'content' => '{{company_name}} - Rapport confidentiel',
                'styleConfig' => [
                    'font-size' => '9pt',
                    'color' => '#666'
                ],
                'sortOrder' => 1,
                'isRequired' => true
            ],
            [
                'name' => 'Pagination Rapport',
                'type' => DocumentUIComponent::TYPE_PAGE_NUMBER,
                'zone' => DocumentUITemplate::ZONE_FOOTER,
                'content' => 'Page {{page_number}} sur {{total_pages}}',
                'styleConfig' => [
                    'font-size' => '9pt',
                    'color' => '#666',
                    'text-align' => 'right'
                ],
                'sortOrder' => 2,
                'isRequired' => true
            ]
        ];

        $this->createComponentsForTemplate($manager, $template, $user, $components);
    }

    /**
     * Helper method to create components for a template
     */
    private function createComponentsForTemplate(
        ObjectManager $manager,
        DocumentUITemplate $template,
        User $user,
        array $componentsData
    ): void {
        foreach ($componentsData as $componentData) {
            $component = new DocumentUIComponent();
            $component->setName($componentData['name'])
                     ->setType($componentData['type'])
                     ->setZone($componentData['zone'])
                     ->setContent($componentData['content'] ?? null)
                     ->setHtmlContent($componentData['htmlContent'] ?? null)
                     ->setStyleConfig($componentData['styleConfig'] ?? [])
                     ->setPositionConfig($componentData['positionConfig'] ?? [])
                     ->setDataBinding($componentData['dataBinding'] ?? null)
                     ->setConditionalDisplay($componentData['conditionalDisplay'] ?? null)
                     ->setIsActive(true)
                     ->setIsRequired($componentData['isRequired'] ?? false)
                     ->setSortOrder($componentData['sortOrder'])
                     ->setCssClass($componentData['cssClass'] ?? null)
                     ->setElementId($componentData['elementId'] ?? null)
                     ->setUiTemplate($template)
                     ->setCreatedBy($user);

            $manager->persist($component);
        }
    }
}
