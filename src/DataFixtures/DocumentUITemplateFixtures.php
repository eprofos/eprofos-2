<?php

namespace App\DataFixtures;

use App\Entity\Document\DocumentUITemplate;
use App\Entity\Document\DocumentType;
use App\Entity\User\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Document UI Template Fixtures - Creates UI layout templates for documents
 * 
 * Provides various pre-configured templates for different document types
 * with professional layouts, styling, and component structures.
 */
class DocumentUITemplateFixtures extends Fixture implements DependentFixtureInterface
{
    // Template references for other fixtures
    public const STANDARD_TEMPLATE_REFERENCE = 'ui-template-standard';
    public const LETTERHEAD_TEMPLATE_REFERENCE = 'ui-template-letterhead';
    public const CERTIFICATE_TEMPLATE_REFERENCE = 'ui-template-certificate';
    public const LEGAL_TEMPLATE_REFERENCE = 'ui-template-legal';
    public const HANDBOOK_TEMPLATE_REFERENCE = 'ui-template-handbook';
    public const QUALITY_TEMPLATE_REFERENCE = 'ui-template-quality';
    public const MINIMAL_TEMPLATE_REFERENCE = 'ui-template-minimal';
    public const REPORT_TEMPLATE_REFERENCE = 'ui-template-report';

    public function load(ObjectManager $manager): void
    {
        $templates = [
            [
                'name' => 'Template Standard EPROFOS',
                'slug' => 'standard-eprofos',
                'description' => 'Template standard avec en-tête et pied de page EPROFOS pour documents généraux',
                'documentType' => null, // Global template
                'isGlobal' => true,
                'isDefault' => true,
                'orientation' => 'portrait',
                'paperSize' => 'A4',
                'marginTop' => 25.0,
                'marginRight' => 20.0,
                'marginBottom' => 25.0,
                'marginLeft' => 20.0,
                'showPageNumbers' => true,
                'icon' => 'fas fa-file-alt',
                'color' => '#007bff',
                'htmlTemplate' => $this->getStandardTemplate(),
                'cssStyles' => $this->getStandardCss(),
                'pageSettings' => [
                    'numbering' => true,
                    'numbering_position' => 'bottom-right',
                    'show_date' => true,
                    'show_watermark' => false
                ],
                'headerFooterConfig' => [
                    'header' => '<div class="header-content">{{company_logo}} <span class="company-name">EPROFOS</span></div>',
                    'footer' => '<div class="footer-content">{{company_address}} | {{company_phone}} | {{company_email}}</div>'
                ],
                'variables' => [
                    'company_logo' => ['type' => 'image', 'default' => '/images/logo-eprofos.png', 'description' => 'Logo de l\'entreprise'],
                    'company_name' => ['type' => 'text', 'default' => 'EPROFOS', 'description' => 'Nom de l\'entreprise'],
                    'company_address' => ['type' => 'text', 'default' => '123 Rue de la Formation, 75000 Paris', 'description' => 'Adresse de l\'entreprise'],
                    'company_phone' => ['type' => 'text', 'default' => '01 23 45 67 89', 'description' => 'Téléphone'],
                    'company_email' => ['type' => 'email', 'default' => 'contact@eprofos.fr', 'description' => 'Email de contact'],
                ],
                'reference' => self::STANDARD_TEMPLATE_REFERENCE,
                'sortOrder' => 1,
                'usageCount' => 15
            ],
            [
                'name' => 'Template Papier à En-tête',
                'slug' => 'letterhead-official',
                'description' => 'Template avec en-tête officiel pour correspondance professionnelle',
                'documentType' => DocumentTypeFixtures::LEGAL_TYPE_REFERENCE,
                'isGlobal' => false,
                'isDefault' => false,
                'orientation' => 'portrait',
                'paperSize' => 'A4',
                'marginTop' => 40.0,
                'marginRight' => 20.0,
                'marginBottom' => 30.0,
                'marginLeft' => 20.0,
                'showPageNumbers' => true,
                'icon' => 'fas fa-file-signature',
                'color' => '#dc3545',
                'htmlTemplate' => $this->getLetterheadTemplate(),
                'cssStyles' => $this->getLetterheadCss(),
                'pageSettings' => [
                    'numbering' => true,
                    'numbering_position' => 'bottom-center',
                    'show_date' => true,
                    'show_watermark' => false
                ],
                'headerFooterConfig' => [
                    'header' => '<div class="letterhead-header">{{official_logo}}<div class="company-info"><h1>{{company_name}}</h1><p>{{company_tagline}}</p></div></div>',
                    'footer' => '<div class="letterhead-footer">{{legal_info}} | {{registration_number}}</div>'
                ],
                'variables' => [
                    'official_logo' => ['type' => 'image', 'default' => '/images/logo-official.png', 'description' => 'Logo officiel'],
                    'company_tagline' => ['type' => 'text', 'default' => 'Excellence en formation professionnelle', 'description' => 'Slogan'],
                    'legal_info' => ['type' => 'text', 'default' => 'SAS au capital de 10 000€', 'description' => 'Informations légales'],
                    'registration_number' => ['type' => 'text', 'default' => 'RCS Paris 123 456 789', 'description' => 'Numéro d\'enregistrement'],
                ],
                'reference' => self::LETTERHEAD_TEMPLATE_REFERENCE,
                'sortOrder' => 2,
                'usageCount' => 8
            ],
            [
                'name' => 'Template Certificat',
                'slug' => 'certificate-professional',
                'description' => 'Template élégant pour certificats et diplômes avec bordures décoratives',
                'documentType' => DocumentTypeFixtures::TRAINING_TYPE_REFERENCE,
                'isGlobal' => false,
                'isDefault' => false,
                'orientation' => 'landscape',
                'paperSize' => 'A4',
                'marginTop' => 15.0,
                'marginRight' => 15.0,
                'marginBottom' => 15.0,
                'marginLeft' => 15.0,
                'showPageNumbers' => false,
                'icon' => 'fas fa-certificate',
                'color' => '#ffc107',
                'htmlTemplate' => $this->getCertificateTemplate(),
                'cssStyles' => $this->getCertificateCss(),
                'pageSettings' => [
                    'numbering' => false,
                    'show_date' => false,
                    'show_watermark' => true,
                    'watermark_text' => 'CERTIFIÉ'
                ],
                'variables' => [
                    'certificate_title' => ['type' => 'text', 'default' => 'CERTIFICAT DE FORMATION', 'description' => 'Titre du certificat'],
                    'recipient_name' => ['type' => 'text', 'default' => '', 'description' => 'Nom du bénéficiaire'],
                    'formation_title' => ['type' => 'text', 'default' => '', 'description' => 'Titre de la formation'],
                    'completion_date' => ['type' => 'date', 'default' => '', 'description' => 'Date de fin de formation'],
                    'duration' => ['type' => 'text', 'default' => '', 'description' => 'Durée de la formation'],
                    'signature_name' => ['type' => 'text', 'default' => 'Directeur Pédagogique', 'description' => 'Nom du signataire'],
                    'signature_image' => ['type' => 'image', 'default' => '', 'description' => 'Image de signature'],
                ],
                'reference' => self::CERTIFICATE_TEMPLATE_REFERENCE,
                'sortOrder' => 3,
                'usageCount' => 12
            ],
            [
                'name' => 'Template Document Légal',
                'slug' => 'legal-document',
                'description' => 'Template formaté pour documents légaux avec numérotation et références',
                'documentType' => DocumentTypeFixtures::LEGAL_TYPE_REFERENCE,
                'isGlobal' => false,
                'isDefault' => true,
                'orientation' => 'portrait',
                'paperSize' => 'A4',
                'marginTop' => 30.0,
                'marginRight' => 25.0,
                'marginBottom' => 30.0,
                'marginLeft' => 25.0,
                'showPageNumbers' => true,
                'icon' => 'fas fa-gavel',
                'color' => '#dc3545',
                'htmlTemplate' => $this->getLegalTemplate(),
                'cssStyles' => $this->getLegalCss(),
                'pageSettings' => [
                    'numbering' => true,
                    'numbering_position' => 'bottom-center',
                    'show_date' => true,
                    'show_watermark' => false
                ],
                'variables' => [
                    'document_reference' => ['type' => 'text', 'default' => '', 'description' => 'Référence du document'],
                    'legal_reference' => ['type' => 'text', 'default' => '', 'description' => 'Référence légale'],
                    'effective_date' => ['type' => 'date', 'default' => '', 'description' => 'Date d\'entrée en vigueur'],
                    'review_date' => ['type' => 'date', 'default' => '', 'description' => 'Date de révision'],
                ],
                'reference' => self::LEGAL_TEMPLATE_REFERENCE,
                'sortOrder' => 4,
                'usageCount' => 6
            ],
            [
                'name' => 'Template Livret d\'Accueil',
                'slug' => 'handbook-welcome',
                'description' => 'Template coloré et accueillant pour livrets étudiants avec sections claires',
                'documentType' => DocumentTypeFixtures::HANDBOOK_TYPE_REFERENCE,
                'isGlobal' => false,
                'isDefault' => true,
                'orientation' => 'portrait',
                'paperSize' => 'A4',
                'marginTop' => 20.0,
                'marginRight' => 20.0,
                'marginBottom' => 20.0,
                'marginLeft' => 20.0,
                'showPageNumbers' => true,
                'icon' => 'fas fa-graduation-cap',
                'color' => '#007bff',
                'htmlTemplate' => $this->getHandbookTemplate(),
                'cssStyles' => $this->getHandbookCss(),
                'pageSettings' => [
                    'numbering' => true,
                    'numbering_position' => 'bottom-right',
                    'show_date' => true,
                    'show_watermark' => false
                ],
                'variables' => [
                    'academic_year' => ['type' => 'text', 'default' => '2024-2025', 'description' => 'Année académique'],
                    'student_name' => ['type' => 'text', 'default' => '', 'description' => 'Nom de l\'étudiant'],
                    'program_name' => ['type' => 'text', 'default' => '', 'description' => 'Nom du programme'],
                    'welcome_message' => ['type' => 'text', 'default' => 'Bienvenue chez EPROFOS !', 'description' => 'Message d\'accueil'],
                ],
                'reference' => self::HANDBOOK_TEMPLATE_REFERENCE,
                'sortOrder' => 5,
                'usageCount' => 4
            ],
            [
                'name' => 'Template Qualité Qualiopi',
                'slug' => 'quality-qualiopi',
                'description' => 'Template conforme aux exigences Qualiopi pour documents qualité',
                'documentType' => DocumentTypeFixtures::QUALITY_TYPE_REFERENCE,
                'isGlobal' => false,
                'isDefault' => true,
                'orientation' => 'portrait',
                'paperSize' => 'A4',
                'marginTop' => 25.0,
                'marginRight' => 20.0,
                'marginBottom' => 25.0,
                'marginLeft' => 20.0,
                'showPageNumbers' => true,
                'icon' => 'fas fa-certificate',
                'color' => '#ffc107',
                'htmlTemplate' => $this->getQualityTemplate(),
                'cssStyles' => $this->getQualityCss(),
                'pageSettings' => [
                    'numbering' => true,
                    'numbering_position' => 'bottom-center',
                    'show_date' => true,
                    'show_watermark' => true,
                    'watermark_text' => 'QUALIOPI'
                ],
                'variables' => [
                    'qualiopi_criterion' => ['type' => 'text', 'default' => '', 'description' => 'Critère Qualiopi'],
                    'version_number' => ['type' => 'text', 'default' => '1.0', 'description' => 'Numéro de version'],
                    'audit_date' => ['type' => 'date', 'default' => '', 'description' => 'Date d\'audit'],
                    'responsible_person' => ['type' => 'text', 'default' => '', 'description' => 'Responsable qualité'],
                ],
                'reference' => self::QUALITY_TEMPLATE_REFERENCE,
                'sortOrder' => 6,
                'usageCount' => 3
            ],
            [
                'name' => 'Template Minimal',
                'slug' => 'minimal-clean',
                'description' => 'Template épuré sans en-tête ni pied de page pour documents simples',
                'documentType' => null,
                'isGlobal' => true,
                'isDefault' => false,
                'orientation' => 'portrait',
                'paperSize' => 'A4',
                'marginTop' => 20.0,
                'marginRight' => 20.0,
                'marginBottom' => 20.0,
                'marginLeft' => 20.0,
                'showPageNumbers' => false,
                'icon' => 'fas fa-file',
                'color' => '#6c757d',
                'htmlTemplate' => $this->getMinimalTemplate(),
                'cssStyles' => $this->getMinimalCss(),
                'pageSettings' => [
                    'numbering' => false,
                    'show_date' => false,
                    'show_watermark' => false
                ],
                'variables' => [],
                'reference' => self::MINIMAL_TEMPLATE_REFERENCE,
                'sortOrder' => 7,
                'usageCount' => 2
            ],
            [
                'name' => 'Template Rapport d\'Activité',
                'slug' => 'activity-report',
                'description' => 'Template professionnel pour rapports avec sections et graphiques',
                'documentType' => DocumentTypeFixtures::PROCEDURE_TYPE_REFERENCE,
                'isGlobal' => false,
                'isDefault' => false,
                'orientation' => 'portrait',
                'paperSize' => 'A4',
                'marginTop' => 25.0,
                'marginRight' => 20.0,
                'marginBottom' => 25.0,
                'marginLeft' => 20.0,
                'showPageNumbers' => true,
                'icon' => 'fas fa-chart-bar',
                'color' => '#28a745',
                'htmlTemplate' => $this->getReportTemplate(),
                'cssStyles' => $this->getReportCss(),
                'pageSettings' => [
                    'numbering' => true,
                    'numbering_position' => 'bottom-right',
                    'show_date' => true,
                    'show_watermark' => false
                ],
                'variables' => [
                    'report_title' => ['type' => 'text', 'default' => 'Rapport d\'Activité', 'description' => 'Titre du rapport'],
                    'report_period' => ['type' => 'text', 'default' => '', 'description' => 'Période du rapport'],
                    'author_name' => ['type' => 'text', 'default' => '', 'description' => 'Nom de l\'auteur'],
                    'department' => ['type' => 'text', 'default' => '', 'description' => 'Département'],
                ],
                'reference' => self::REPORT_TEMPLATE_REFERENCE,
                'sortOrder' => 8,
                'usageCount' => 1
            ],
        ];

        foreach ($templates as $templateData) {
            $template = new DocumentUITemplate();
            $template->setName($templateData['name'])
                    ->setSlug($templateData['slug'])
                    ->setDescription($templateData['description'])
                    ->setDocumentType($templateData['documentType'] ? $this->getReference($templateData['documentType'], DocumentType::class) : null)
                    ->setHtmlTemplate($templateData['htmlTemplate'])
                    ->setCssStyles($templateData['cssStyles'])
                    ->setPageSettings($templateData['pageSettings'])
                    ->setHeaderFooterConfig($templateData['headerFooterConfig'] ?? null)
                    ->setVariables($templateData['variables'])
                    ->setOrientation($templateData['orientation'])
                    ->setPaperSize($templateData['paperSize'])
                    ->setMarginTop($templateData['marginTop'])
                    ->setMarginRight($templateData['marginRight'])
                    ->setMarginBottom($templateData['marginBottom'])
                    ->setMarginLeft($templateData['marginLeft'])
                    ->setShowPageNumbers($templateData['showPageNumbers'])
                    ->setIcon($templateData['icon'])
                    ->setColor($templateData['color'])
                    ->setIsActive(true)
                    ->setIsDefault($templateData['isDefault'])
                    ->setIsGlobal($templateData['isGlobal'])
                    ->setSortOrder($templateData['sortOrder'])
                    ->setUsageCount($templateData['usageCount'])
                    ->setCreatedBy($this->getReference(UserFixtures::ADMIN_USER_REFERENCE, User::class));

            $manager->persist($template);
            $this->addReference($templateData['reference'], $template);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            DocumentTypeFixtures::class,
        ];
    }

    /**
     * Template HTML definitions
     */
    private function getStandardTemplate(): string
    {
        return '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{title}}</title>
    <style>{{css}}</style>
</head>
<body>
    <header class="document-header">
        <div class="header-logo">{{company_logo}}</div>
        <div class="header-info">
            <h1 class="company-name">{{company_name}}</h1>
            <p class="document-type">{{document_type}}</p>
        </div>
        <div class="header-date">{{date}}</div>
    </header>
    
    <main class="document-content">
        <h1 class="document-title">{{title}}</h1>
        <div class="document-body">
            {{content}}
        </div>
    </main>
    
    <footer class="document-footer">
        <div class="footer-info">
            <span>{{company_address}}</span>
            <span>{{company_phone}}</span>
            <span>{{company_email}}</span>
        </div>
        <div class="footer-page">Page {{page_number}} sur {{total_pages}}</div>
    </footer>
</body>
</html>';
    }

    private function getStandardCss(): string
    {
        return '@page {
    size: A4 portrait;
    margin: 25mm 20mm 25mm 20mm;
}

body {
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    font-size: 11pt;
    line-height: 1.4;
    color: #333;
    margin: 0;
    padding: 0;
}

.document-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 2px solid #007bff;
    padding-bottom: 10mm;
    margin-bottom: 15mm;
}

.header-logo img {
    max-height: 40px;
    max-width: 120px;
}

.company-name {
    color: #007bff;
    font-size: 18pt;
    font-weight: bold;
    margin: 0;
}

.document-type {
    color: #666;
    font-size: 10pt;
    margin: 2px 0 0 0;
}

.header-date {
    font-size: 10pt;
    color: #666;
}

.document-title {
    color: #007bff;
    font-size: 16pt;
    font-weight: bold;
    text-align: center;
    margin-bottom: 15mm;
}

.document-content {
    min-height: 200mm;
}

.document-body {
    text-align: justify;
}

.document-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid #ddd;
    padding-top: 5mm;
    margin-top: 15mm;
    font-size: 9pt;
    color: #666;
}

.footer-info span {
    margin-right: 15px;
}

h1, h2, h3, h4, h5, h6 {
    color: #007bff;
    font-weight: bold;
}

h2 { font-size: 14pt; margin: 10mm 0 5mm 0; }
h3 { font-size: 12pt; margin: 8mm 0 4mm 0; }
h4 { font-size: 11pt; margin: 6mm 0 3mm 0; }

p {
    margin: 3mm 0;
    text-align: justify;
}

ul, ol {
    margin: 3mm 0 3mm 8mm;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin: 5mm 0;
}

table th,
table td {
    border: 1px solid #ddd;
    padding: 3mm;
    text-align: left;
}

table th {
    background-color: #f8f9fa;
    font-weight: bold;
}';
    }

    private function getLetterheadTemplate(): string
    {
        return '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{title}}</title>
    <style>{{css}}</style>
</head>
<body>
    <header class="letterhead-header">
        <div class="logo-section">{{official_logo}}</div>
        <div class="company-section">
            <h1 class="company-name">{{company_name}}</h1>
            <p class="company-tagline">{{company_tagline}}</p>
            <div class="contact-info">
                <p>{{company_address}}</p>
                <p>{{company_phone}} | {{company_email}}</p>
            </div>
        </div>
    </header>
    
    <div class="document-date">{{date}}</div>
    
    <main class="letter-content">
        <h2 class="document-title">{{title}}</h2>
        <div class="document-body">
            {{content}}
        </div>
    </main>
    
    <footer class="letterhead-footer">
        <div class="legal-info">
            <p>{{legal_info}} | {{registration_number}}</p>
        </div>
        <div class="page-info">Page {{page_number}}</div>
    </footer>
</body>
</html>';
    }

    private function getLetterheadCss(): string
    {
        return '@page {
    size: A4 portrait;
    margin: 40mm 20mm 30mm 20mm;
}

body {
    font-family: "Times New Roman", serif;
    font-size: 12pt;
    line-height: 1.5;
    color: #000;
    margin: 0;
    padding: 0;
}

.letterhead-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 3px solid #dc3545;
    padding-bottom: 8mm;
    margin-bottom: 10mm;
}

.logo-section img {
    max-height: 60px;
    max-width: 100px;
}

.company-section {
    text-align: right;
    flex: 1;
    margin-left: 20mm;
}

.company-name {
    color: #dc3545;
    font-size: 24pt;
    font-weight: bold;
    margin: 0;
}

.company-tagline {
    color: #666;
    font-style: italic;
    font-size: 11pt;
    margin: 2mm 0 5mm 0;
}

.contact-info {
    font-size: 10pt;
    color: #333;
}

.contact-info p {
    margin: 1mm 0;
}

.document-date {
    text-align: right;
    font-size: 11pt;
    margin-bottom: 10mm;
}

.document-title {
    font-size: 16pt;
    font-weight: bold;
    color: #dc3545;
    text-align: center;
    margin: 8mm 0;
    text-transform: uppercase;
}

.letter-content {
    min-height: 150mm;
}

.document-body {
    text-align: justify;
    text-indent: 8mm;
}

.letterhead-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid #dc3545;
    padding-top: 3mm;
    margin-top: 10mm;
    font-size: 9pt;
    color: #666;
}

p {
    margin: 4mm 0;
    text-align: justify;
}';
    }

    private function getCertificateTemplate(): string
    {
        return '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{certificate_title}}</title>
    <style>{{css}}</style>
</head>
<body>
    <div class="certificate-container">
        <div class="certificate-border">
            <div class="certificate-header">
                <div class="logo">{{company_logo}}</div>
                <h1 class="certificate-title">{{certificate_title}}</h1>
            </div>
            
            <div class="certificate-content">
                <p class="certification-text">Nous certifions par la présente que</p>
                <h2 class="recipient-name">{{recipient_name}}</h2>
                <p class="completion-text">a suivi avec succès la formation</p>
                <h3 class="formation-title">{{formation_title}}</h3>
                <div class="formation-details">
                    <p>d\'une durée de <strong>{{duration}}</strong></p>
                    <p>achevée le <strong>{{completion_date}}</strong></p>
                </div>
            </div>
            
            <div class="certificate-footer">
                <div class="signature-section">
                    <div class="signature">
                        {{signature_image}}
                        <div class="signature-line"></div>
                        <p class="signature-name">{{signature_name}}</p>
                    </div>
                </div>
                <div class="date-section">
                    <p>Délivré le {{date}}</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>';
    }

    private function getCertificateCss(): string
    {
        return '@page {
    size: A4 landscape;
    margin: 15mm;
}

body {
    font-family: "Georgia", serif;
    margin: 0;
    padding: 0;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
}

.certificate-container {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.certificate-border {
    width: 95%;
    height: 95%;
    border: 8px solid #ffc107;
    border-radius: 15px;
    padding: 20mm;
    background: white;
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
    text-align: center;
    position: relative;
}

.certificate-border::before {
    content: "";
    position: absolute;
    top: 15px;
    left: 15px;
    right: 15px;
    bottom: 15px;
    border: 2px solid #ffc107;
    border-radius: 8px;
}

.certificate-header {
    margin-bottom: 20mm;
}

.logo img {
    max-height: 50px;
    margin-bottom: 10mm;
}

.certificate-title {
    font-size: 28pt;
    font-weight: bold;
    color: #ffc107;
    margin: 0;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
}

.certificate-content {
    margin: 15mm 0;
}

.certification-text,
.completion-text {
    font-size: 14pt;
    color: #333;
    margin: 5mm 0;
}

.recipient-name {
    font-size: 24pt;
    font-weight: bold;
    color: #007bff;
    margin: 10mm 0;
    text-decoration: underline;
    text-decoration-color: #ffc107;
}

.formation-title {
    font-size: 18pt;
    font-weight: bold;
    color: #dc3545;
    margin: 8mm 0;
    font-style: italic;
}

.formation-details {
    font-size: 12pt;
    color: #666;
    margin: 8mm 0;
}

.certificate-footer {
    position: absolute;
    bottom: 15mm;
    left: 20mm;
    right: 20mm;
    display: flex;
    justify-content: space-between;
    align-items: end;
}

.signature-section {
    text-align: center;
}

.signature img {
    max-height: 40px;
    margin-bottom: 2mm;
}

.signature-line {
    width: 100px;
    height: 1px;
    background: #333;
    margin: 5mm auto 2mm;
}

.signature-name {
    font-size: 11pt;
    font-weight: bold;
    color: #333;
    margin: 0;
}

.date-section {
    font-size: 10pt;
    color: #666;
}';
    }

    private function getLegalTemplate(): string
    {
        return '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{title}}</title>
    <style>{{css}}</style>
</head>
<body>
    <header class="legal-header">
        <div class="document-reference">
            <span>Réf: {{document_reference}}</span>
            <span>{{legal_reference}}</span>
        </div>
        <div class="dates">
            <span>En vigueur: {{effective_date}}</span>
            <span>Révision: {{review_date}}</span>
        </div>
    </header>
    
    <main class="legal-content">
        <h1 class="document-title">{{title}}</h1>
        <div class="document-body">
            {{content}}
        </div>
    </main>
    
    <footer class="legal-footer">
        <div class="footer-left">{{company_name}} - Document confidentiel</div>
        <div class="footer-right">Page {{page_number}} sur {{total_pages}}</div>
    </footer>
</body>
</html>';
    }

    private function getLegalCss(): string
    {
        return '@page {
    size: A4 portrait;
    margin: 30mm 25mm;
}

body {
    font-family: "Arial", sans-serif;
    font-size: 11pt;
    line-height: 1.6;
    color: #000;
    margin: 0;
    padding: 0;
}

.legal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid #dc3545;
    padding-bottom: 5mm;
    margin-bottom: 10mm;
    font-size: 9pt;
    color: #666;
}

.document-reference span,
.dates span {
    display: block;
    margin: 1mm 0;
}

.document-title {
    font-size: 18pt;
    font-weight: bold;
    color: #dc3545;
    text-align: center;
    margin: 10mm 0;
    text-transform: uppercase;
}

.legal-content {
    min-height: 200mm;
}

.document-body {
    text-align: justify;
}

.legal-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid #dc3545;
    padding-top: 3mm;
    margin-top: 10mm;
    font-size: 9pt;
    color: #666;
}

h1, h2, h3, h4 {
    color: #dc3545;
    font-weight: bold;
}

h2 { 
    font-size: 14pt; 
    margin: 8mm 0 4mm 0;
    counter-increment: section;
    counter-reset: subsection;
}

h2::before {
    content: counter(section) ". ";
}

h3 { 
    font-size: 12pt; 
    margin: 6mm 0 3mm 0;
    counter-increment: subsection;
}

h3::before {
    content: counter(section) "." counter(subsection) " ";
}

ol {
    counter-reset: item;
}

ol li {
    display: block;
    margin: 2mm 0;
}

ol li:before {
    content: counter(item, decimal) ". ";
    counter-increment: item;
    font-weight: bold;
    color: #dc3545;
}

.article {
    margin: 5mm 0;
    padding-left: 5mm;
    border-left: 3px solid #dc3545;
}

.article-title {
    font-weight: bold;
    color: #dc3545;
    margin-bottom: 2mm;
}';
    }

    private function getHandbookTemplate(): string
    {
        return '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{title}}</title>
    <style>{{css}}</style>
</head>
<body>
    <header class="handbook-header">
        <div class="welcome-banner">
            <h1>{{welcome_message}}</h1>
            <p class="academic-year">Année {{academic_year}}</p>
        </div>
    </header>
    
    <main class="handbook-content">
        <div class="student-info">
            <h2>Informations personnelles</h2>
            <p><strong>Nom:</strong> {{student_name}}</p>
            <p><strong>Programme:</strong> {{program_name}}</p>
        </div>
        
        <div class="document-body">
            {{content}}
        </div>
    </main>
    
    <footer class="handbook-footer">
        <div class="footer-contact">{{company_name}} | {{company_phone}} | {{company_email}}</div>
        <div class="footer-page">Page {{page_number}}</div>
    </footer>
</body>
</html>';
    }

    private function getHandbookCss(): string
    {
        return '@page {
    size: A4 portrait;
    margin: 20mm;
}

body {
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    font-size: 11pt;
    line-height: 1.5;
    color: #333;
    margin: 0;
    padding: 0;
}

.handbook-header {
    text-align: center;
    margin-bottom: 15mm;
}

.welcome-banner {
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
    padding: 10mm;
    border-radius: 10px;
    margin-bottom: 10mm;
}

.welcome-banner h1 {
    font-size: 20pt;
    margin: 0 0 3mm 0;
}

.academic-year {
    font-size: 12pt;
    margin: 0;
    opacity: 0.9;
}

.student-info {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    padding: 8mm;
    margin-bottom: 10mm;
}

.student-info h2 {
    color: #007bff;
    font-size: 14pt;
    margin: 0 0 5mm 0;
}

.student-info p {
    margin: 2mm 0;
    font-size: 11pt;
}

.handbook-content {
    min-height: 180mm;
}

.handbook-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 2px solid #007bff;
    padding-top: 3mm;
    margin-top: 10mm;
    font-size: 9pt;
    color: #666;
}

h1, h2, h3 {
    color: #007bff;
}

h2 {
    font-size: 14pt;
    margin: 8mm 0 4mm 0;
    padding-bottom: 2mm;
    border-bottom: 1px solid #dee2e6;
}

h3 {
    font-size: 12pt;
    margin: 6mm 0 3mm 0;
}

.info-box {
    background: #e3f2fd;
    border-left: 4px solid #2196f3;
    padding: 5mm;
    margin: 5mm 0;
}

.warning-box {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 5mm;
    margin: 5mm 0;
}

.tip-box {
    background: #d4edda;
    border-left: 4px solid #28a745;
    padding: 5mm;
    margin: 5mm 0;
}';
    }

    private function getQualityTemplate(): string
    {
        return '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{title}}</title>
    <style>{{css}}</style>
</head>
<body>
    <header class="quality-header">
        <div class="qualiopi-badge">
            <span class="badge-text">QUALIOPI</span>
            <span class="criterion">{{qualiopi_criterion}}</span>
        </div>
        <div class="document-info">
            <h1 class="document-title">{{title}}</h1>
            <div class="version-info">
                <span>Version {{version_number}}</span>
                <span>{{date}}</span>
            </div>
        </div>
    </header>
    
    <main class="quality-content">
        <div class="audit-info">
            <h2>Informations audit</h2>
            <p><strong>Date d\'audit:</strong> {{audit_date}}</p>
            <p><strong>Responsable qualité:</strong> {{responsible_person}}</p>
        </div>
        
        <div class="document-body">
            {{content}}
        </div>
    </main>
    
    <footer class="quality-footer">
        <div class="footer-quality">Document qualité certifié Qualiopi</div>
        <div class="footer-page">Page {{page_number}} sur {{total_pages}}</div>
    </footer>
</body>
</html>';
    }

    private function getQualityCss(): string
    {
        return '@page {
    size: A4 portrait;
    margin: 25mm 20mm;
}

body {
    font-family: "Arial", sans-serif;
    font-size: 11pt;
    line-height: 1.5;
    color: #333;
    margin: 0;
    padding: 0;
}

.quality-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 3px solid #ffc107;
    padding-bottom: 8mm;
    margin-bottom: 10mm;
}

.qualiopi-badge {
    background: #ffc107;
    color: #000;
    padding: 8mm;
    border-radius: 10px;
    text-align: center;
    min-width: 40mm;
}

.badge-text {
    display: block;
    font-size: 14pt;
    font-weight: bold;
}

.criterion {
    display: block;
    font-size: 9pt;
    margin-top: 2mm;
}

.document-info {
    flex: 1;
    margin-left: 15mm;
}

.document-title {
    color: #ffc107;
    font-size: 16pt;
    font-weight: bold;
    margin: 0 0 3mm 0;
}

.version-info {
    font-size: 10pt;
    color: #666;
}

.version-info span {
    margin-right: 10mm;
}

.audit-info {
    background: #fff8dc;
    border: 1px solid #ffc107;
    border-radius: 5px;
    padding: 8mm;
    margin-bottom: 10mm;
}

.audit-info h2 {
    color: #ffc107;
    font-size: 12pt;
    margin: 0 0 5mm 0;
}

.audit-info p {
    margin: 2mm 0;
    font-size: 10pt;
}

.quality-content {
    min-height: 180mm;
}

.quality-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 2px solid #ffc107;
    padding-top: 3mm;
    margin-top: 10mm;
    font-size: 9pt;
    color: #666;
}

h1, h2, h3 {
    color: #ffc107;
    font-weight: bold;
}

h2 {
    font-size: 14pt;
    margin: 8mm 0 4mm 0;
}

h3 {
    font-size: 12pt;
    margin: 6mm 0 3mm 0;
}

.quality-indicator {
    background: #f0f8ff;
    border-left: 4px solid #007bff;
    padding: 5mm;
    margin: 5mm 0;
}

.compliance-note {
    background: #f0fff0;
    border: 1px solid #28a745;
    border-radius: 3px;
    padding: 5mm;
    margin: 5mm 0;
    font-size: 10pt;
}';
    }

    private function getMinimalTemplate(): string
    {
        return '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{title}}</title>
    <style>{{css}}</style>
</head>
<body>
    <main class="document-content">
        <h1 class="document-title">{{title}}</h1>
        <div class="document-body">
            {{content}}
        </div>
    </main>
</body>
</html>';
    }

    private function getMinimalCss(): string
    {
        return '@page {
    size: A4 portrait;
    margin: 20mm;
}

body {
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    font-size: 11pt;
    line-height: 1.4;
    color: #333;
    margin: 0;
    padding: 0;
}

.document-title {
    font-size: 16pt;
    font-weight: bold;
    color: #333;
    margin: 0 0 10mm 0;
    text-align: center;
}

.document-content {
    min-height: 250mm;
}

.document-body {
    text-align: justify;
}

h2 {
    font-size: 14pt;
    margin: 8mm 0 4mm 0;
    color: #555;
}

h3 {
    font-size: 12pt;
    margin: 6mm 0 3mm 0;
    color: #666;
}

p {
    margin: 3mm 0;
    text-align: justify;
}

ul, ol {
    margin: 3mm 0 3mm 8mm;
}';
    }

    private function getReportTemplate(): string
    {
        return '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{report_title}}</title>
    <style>{{css}}</style>
</head>
<body>
    <header class="report-header">
        <div class="report-info">
            <h1 class="report-title">{{report_title}}</h1>
            <p class="report-period">{{report_period}}</p>
            <div class="author-info">
                <span>Auteur: {{author_name}}</span>
                <span>Département: {{department}}</span>
            </div>
        </div>
        <div class="report-date">{{date}}</div>
    </header>
    
    <main class="report-content">
        <div class="document-body">
            {{content}}
        </div>
    </main>
    
    <footer class="report-footer">
        <div class="footer-left">{{company_name}} - Rapport confidentiel</div>
        <div class="footer-right">Page {{page_number}} sur {{total_pages}}</div>
    </footer>
</body>
</html>';
    }

    private function getReportCss(): string
    {
        return '@page {
    size: A4 portrait;
    margin: 25mm 20mm;
}

body {
    font-family: "Calibri", "Arial", sans-serif;
    font-size: 11pt;
    line-height: 1.4;
    color: #333;
    margin: 0;
    padding: 0;
}

.report-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 3px solid #28a745;
    padding-bottom: 8mm;
    margin-bottom: 12mm;
}

.report-title {
    font-size: 18pt;
    font-weight: bold;
    color: #28a745;
    margin: 0 0 3mm 0;
}

.report-period {
    font-size: 12pt;
    color: #666;
    font-style: italic;
    margin: 0 0 5mm 0;
}

.author-info {
    font-size: 10pt;
    color: #666;
}

.author-info span {
    display: block;
    margin: 1mm 0;
}

.report-date {
    font-size: 10pt;
    color: #666;
    text-align: right;
}

.report-content {
    min-height: 200mm;
}

.report-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid #28a745;
    padding-top: 3mm;
    margin-top: 12mm;
    font-size: 9pt;
    color: #666;
}

h1, h2, h3 {
    color: #28a745;
    font-weight: bold;
}

h2 {
    font-size: 14pt;
    margin: 10mm 0 5mm 0;
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 2mm;
}

h3 {
    font-size: 12pt;
    margin: 8mm 0 4mm 0;
}

.summary-box {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    padding: 8mm;
    margin: 8mm 0;
}

.kpi-box {
    background: #e8f5e8;
    border-left: 4px solid #28a745;
    padding: 5mm;
    margin: 5mm 0;
}

.chart-placeholder {
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    height: 80mm;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #666;
    font-style: italic;
    margin: 8mm 0;
}';
    }
}
