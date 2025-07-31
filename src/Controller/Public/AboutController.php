<?php

declare(strict_types=1);

namespace App\Controller\Public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * About controller for EPROFOS presentation.
 *
 * Handles the display of information about EPROFOS organization,
 * its mission, values, team, and history.
 */
class AboutController extends AbstractController
{
    /**
     * Display the about page with EPROFOS presentation.
     */
    #[Route('/a-propos', name: 'public_about', methods: ['GET'])]
    public function index(): Response
    {
        // Static content for EPROFOS presentation
        $organizationInfo = [
            'name' => 'EPROFOS',
            'full_name' => 'École professionnelle de formation spécialisée',
            'founded_year' => 2009,
            'mission' => 'Accompagner les professionnels et les entreprises dans leur développement de compétences à travers des formations de qualité adaptées aux besoins du marché.',
            'vision' => 'Être le partenaire de référence en formation professionnelle, reconnu pour l\'excellence de nos programmes et l\'accompagnement personnalisé de nos apprenants.',
            'values' => [
                'Excellence' => 'Nous nous engageons à fournir des formations de la plus haute qualité.',
                'Innovation' => 'Nous intégrons les dernières technologies et méthodes pédagogiques.',
                'Proximité' => 'Nous offrons un accompagnement personnalisé à chaque apprenant.',
                'Adaptabilité' => 'Nous adaptons nos formations aux évolutions du marché du travail.',
            ],
            'certifications' => [
                'Qualiopi',
                'Datadock',
                'OPCO partenaire',
                'Certification ISO 9001',
            ],
            'key_figures' => [
                'formations_delivered' => 500,
                'satisfied_clients' => 1200,
                'corporate_partners' => 150,
                'success_rate' => 95,
            ],
        ];

        $team = [
            [
                'name' => 'Marie Dubois',
                'position' => 'Directrice Générale',
                'experience' => '15 ans d\'expérience en formation professionnelle',
                'specialties' => ['Management', 'Stratégie d\'entreprise'],
            ],
            [
                'name' => 'Jean Martin',
                'position' => 'Responsable Pédagogique',
                'experience' => '12 ans d\'expérience en ingénierie pédagogique',
                'specialties' => ['Formations techniques', 'E-learning'],
            ],
            [
                'name' => 'Sophie Laurent',
                'position' => 'Responsable Commercial',
                'experience' => '10 ans d\'expérience en développement commercial',
                'specialties' => ['Relation client', 'Formations sur mesure'],
            ],
        ];

        return $this->render('public/about/index.html.twig', [
            'organization' => $organizationInfo,
            'team' => $team,
        ]);
    }

    /**
     * Display our methodology and approach.
     */
    #[Route('/notre-approche', name: 'public_about_approach', methods: ['GET'])]
    public function approach(): Response
    {
        $methodology = [
            'analysis' => [
                'title' => 'Analyse des besoins',
                'description' => 'Nous commençons par une analyse approfondie de vos besoins spécifiques et de votre contexte professionnel.',
                'steps' => [
                    'Entretien de diagnostic',
                    'Évaluation des compétences actuelles',
                    'Définition des objectifs pédagogiques',
                    'Identification des contraintes',
                ],
            ],
            'design' => [
                'title' => 'Conception sur mesure',
                'description' => 'Nous concevons des programmes de formation adaptés à vos objectifs et contraintes.',
                'steps' => [
                    'Élaboration du programme pédagogique',
                    'Sélection des méthodes d\'apprentissage',
                    'Création des supports de formation',
                    'Planification des sessions',
                ],
            ],
            'delivery' => [
                'title' => 'Mise en œuvre',
                'description' => 'Nous déployons la formation avec un accompagnement personnalisé tout au long du parcours.',
                'steps' => [
                    'Animation des sessions de formation',
                    'Suivi individualisé des apprenants',
                    'Évaluations régulières',
                    'Ajustements en temps réel',
                ],
            ],
            'follow_up' => [
                'title' => 'Suivi et évaluation',
                'description' => 'Nous mesurons l\'efficacité de la formation et assurons un suivi post-formation.',
                'steps' => [
                    'Évaluation des acquis',
                    'Mesure de la satisfaction',
                    'Suivi de la mise en pratique',
                    'Accompagnement post-formation',
                ],
            ],
        ];

        return $this->render('public/about/approach.html.twig', [
            'methodology' => $methodology,
        ]);
    }
}
