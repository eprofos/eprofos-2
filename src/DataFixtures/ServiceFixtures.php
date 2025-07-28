<?php

namespace App\DataFixtures;

use App\Entity\Service\Service;
use App\Entity\Service\ServiceCategory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Service fixtures for EPROFOS platform
 * 
 * Creates realistic services for each service category including
 * consulting, custom training, certification, coaching, and audit services.
 */
class ServiceFixtures extends Fixture implements DependentFixtureInterface
{
    private SluggerInterface $slugger;

    public function __construct(SluggerInterface $slugger)
    {
        $this->slugger = $slugger;
    }

    /**
     * Load service fixtures
     */
    public function load(ObjectManager $manager): void
    {
        $services = [
            // Conseil et Expertise
            [
                'title' => 'Audit de Compétences et Plan de Formation',
                'description' => 'Évaluation complète des compétences de vos équipes et élaboration d\'un plan de formation personnalisé pour optimiser les performances.',
                'benefits' => "Identification précise des besoins en formation\nOptimisation du budget formation\nAmélioration de la performance des équipes\nPlan de développement individualisé\nSuivi et mesure des progrès",
                'icon' => 'fas fa-search',
                'serviceCategory' => ServiceCategoryFixtures::SERVICE_CATEGORY_CONSEIL,
            ],
            [
                'title' => 'Conseil en Transformation Digitale',
                'description' => 'Accompagnement stratégique pour réussir votre transformation digitale et moderniser vos processus métier.',
                'benefits' => "Diagnostic digital complet\nStratégie de transformation adaptée\nAccompagnement au changement\nFormation des équipes aux nouveaux outils\nOptimisation des processus",
                'icon' => 'fas fa-digital-tachograph',
                'serviceCategory' => ServiceCategoryFixtures::SERVICE_CATEGORY_CONSEIL,
            ],
            [
                'title' => 'Conseil en Organisation et Méthodes',
                'description' => 'Optimisation de votre organisation et de vos méthodes de travail pour améliorer l\'efficacité opérationnelle.',
                'benefits' => "Analyse des processus existants\nIdentification des axes d'amélioration\nRecommandations d'optimisation\nAccompagnement à la mise en œuvre\nMesure des gains obtenus",
                'icon' => 'fas fa-cogs',
                'serviceCategory' => ServiceCategoryFixtures::SERVICE_CATEGORY_CONSEIL,
            ],

            // Formation Sur Mesure
            [
                'title' => 'Formation Intra-Entreprise Personnalisée',
                'description' => 'Formations sur mesure adaptées aux besoins spécifiques de votre entreprise et dispensées dans vos locaux.',
                'benefits' => "Contenu adapté à vos enjeux\nFormation dans vos locaux\nHoraires flexibles\nGroupe homogène de collaborateurs\nTarif dégressif selon le nombre de participants",
                'icon' => 'fas fa-chalkboard-teacher',
                'serviceCategory' => ServiceCategoryFixtures::SERVICE_CATEGORY_FORMATION,
            ],
            [
                'title' => 'Conception de Parcours de Formation',
                'description' => 'Création de parcours de formation complets et progressifs pour développer les compétences de vos collaborateurs.',
                'benefits' => "Parcours personnalisés\nProgression pédagogique adaptée\nMélange de modalités (présentiel, e-learning)\nSuivi individualisé\nÉvaluation des acquis",
                'icon' => 'fas fa-route',
                'serviceCategory' => ServiceCategoryFixtures::SERVICE_CATEGORY_FORMATION,
            ],
            [
                'title' => 'Formation de Formateurs Internes',
                'description' => 'Développez les compétences pédagogiques de vos experts internes pour créer une dynamique de formation continue.',
                'benefits' => "Autonomie en formation interne\nTransmission des savoirs experts\nRéduction des coûts de formation\nCulture d'apprentissage renforcée\nCapitalisation des connaissances",
                'icon' => 'fas fa-user-graduate',
                'serviceCategory' => ServiceCategoryFixtures::SERVICE_CATEGORY_FORMATION,
            ],

            // Certification et Validation
            [
                'title' => 'Préparation aux Certifications Professionnelles',
                'description' => 'Accompagnement personnalisé pour préparer et réussir vos certifications professionnelles reconnues.',
                'benefits' => "Préparation ciblée aux examens\nSimulations et tests blancs\nSuivi personnalisé\nTaux de réussite élevé\nReconnaissance professionnelle",
                'icon' => 'fas fa-certificate',
                'serviceCategory' => ServiceCategoryFixtures::SERVICE_CATEGORY_CERTIFICATION,
            ],
            [
                'title' => 'Validation des Acquis de l\'Expérience (VAE)',
                'description' => 'Accompagnement complet pour valoriser votre expérience professionnelle et obtenir une certification officielle.',
                'benefits' => "Reconnaissance officielle des compétences\nAccompagnement personnalisé\nConstitution du dossier VAE\nPréparation à la soutenance\nValoristion de l'expérience",
                'icon' => 'fas fa-medal',
                'serviceCategory' => ServiceCategoryFixtures::SERVICE_CATEGORY_CERTIFICATION,
            ],
            [
                'title' => 'Évaluation et Certification Interne',
                'description' => 'Mise en place de systèmes d\'évaluation et de certification interne pour valider les compétences de vos équipes.',
                'benefits' => "Référentiel de compétences personnalisé\nOutils d'évaluation adaptés\nProcessus de certification interne\nSuivi des progressions\nMotivation des équipes",
                'icon' => 'fas fa-clipboard-check',
                'serviceCategory' => ServiceCategoryFixtures::SERVICE_CATEGORY_CERTIFICATION,
            ],

            // Accompagnement et Coaching
            [
                'title' => 'Coaching Individuel de Dirigeants',
                'description' => 'Accompagnement personnalisé des dirigeants pour développer leur leadership et optimiser leur performance.',
                'benefits' => "Développement du leadership\nGestion du stress et des priorités\nAmélioration de la communication\nPrise de décision éclairée\nÉquilibre vie professionnelle/personnelle",
                'icon' => 'fas fa-user-tie',
                'serviceCategory' => ServiceCategoryFixtures::SERVICE_CATEGORY_ACCOMPAGNEMENT,
            ],
            [
                'title' => 'Accompagnement au Changement',
                'description' => 'Support méthodologique et humain pour réussir vos projets de transformation et gérer les résistances.',
                'benefits' => "Stratégie de conduite du changement\nCommunication adaptée\nGestion des résistances\nMobilisation des équipes\nPérennisation des acquis",
                'icon' => 'fas fa-exchange-alt',
                'serviceCategory' => ServiceCategoryFixtures::SERVICE_CATEGORY_ACCOMPAGNEMENT,
            ],
            [
                'title' => 'Coaching d\'Équipe et Team Building',
                'description' => 'Renforcement de la cohésion d\'équipe et amélioration de la collaboration pour optimiser la performance collective.',
                'benefits' => "Amélioration de la cohésion\nOptimisation de la communication\nRésolution des conflits\nDéfinition d'objectifs communs\nRenforcement de la motivation",
                'icon' => 'fas fa-users',
                'serviceCategory' => ServiceCategoryFixtures::SERVICE_CATEGORY_ACCOMPAGNEMENT,
            ],

            // Audit et Diagnostic
            [
                'title' => 'Audit de Performance Organisationnelle',
                'description' => 'Évaluation complète de votre organisation pour identifier les leviers d\'amélioration de la performance.',
                'benefits' => "Diagnostic complet de l'organisation\nIdentification des dysfonctionnements\nRecommandations d'amélioration\nPlan d'actions prioritaires\nSuivi de la mise en œuvre",
                'icon' => 'fas fa-chart-line',
                'serviceCategory' => ServiceCategoryFixtures::SERVICE_CATEGORY_AUDIT,
            ],
            [
                'title' => 'Audit de Conformité et Qualité',
                'description' => 'Vérification de la conformité de vos processus aux normes et réglementations en vigueur.',
                'benefits' => "Vérification de la conformité\nIdentification des écarts\nPlan de mise en conformité\nPréparation aux audits externes\nAmélioration continue",
                'icon' => 'fas fa-clipboard-list',
                'serviceCategory' => ServiceCategoryFixtures::SERVICE_CATEGORY_AUDIT,
            ],
            [
                'title' => 'Diagnostic Digital et Cybersécurité',
                'description' => 'Évaluation de votre maturité digitale et de votre niveau de sécurité informatique avec recommandations d\'amélioration.',
                'benefits' => "Évaluation de la maturité digitale\nAnalyse des vulnérabilités\nRecommandations sécuritaires\nPlan de transformation digital\nSensibilisation des équipes",
                'icon' => 'fas fa-shield-alt',
                'serviceCategory' => ServiceCategoryFixtures::SERVICE_CATEGORY_AUDIT,
            ],
        ];

        foreach ($services as $serviceData) {
            $service = new Service();
            $service->setTitle($serviceData['title']);
            $service->setSlug($this->slugger->slug($serviceData['title'])->lower());
            $service->setDescription($serviceData['description']);
            $service->setBenefits($serviceData['benefits']);
            $service->setIcon($serviceData['icon']);
            $service->setIsActive(true);
            
            // Set service category reference
            $serviceCategory = $this->getReference($serviceData['serviceCategory'], ServiceCategory::class);
            $service->setServiceCategory($serviceCategory);

            $manager->persist($service);
        }

        $manager->flush();
    }

    /**
     * Define fixture dependencies
     */
    public function getDependencies(): array
    {
        return [
            ServiceCategoryFixtures::class,
        ];
    }
}