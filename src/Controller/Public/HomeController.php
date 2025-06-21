<?php

namespace App\Controller\Public;

use App\Repository\FormationRepository;
use App\Repository\ServiceRepository;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Home controller for the public homepage
 * 
 * Displays featured formations, main services, and general
 * presentation of EPROFOS training organization.
 */
class HomeController extends AbstractController
{
    public function __construct(
        private FormationRepository $formationRepository,
        private ServiceRepository $serviceRepository,
        private CategoryRepository $categoryRepository
    ) {
    }

    /**
     * Display the homepage with featured content
     */
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        // Get featured formations for homepage showcase
        $featuredFormations = $this->formationRepository->findFeaturedFormations(6);
        
        // Get main services to highlight
        $featuredServices = $this->serviceRepository->findFeaturedServices(4);
        
        // Get active categories with formation count for navigation
        $categoriesWithCount = $this->categoryRepository->findActiveCategoriesWithFormationCount();
        
        // Get some statistics for the homepage
        $totalFormations = count($this->formationRepository->findActiveFormations());
        $totalCategories = count($this->categoryRepository->findActiveCategories());

        return $this->render('public/home/index.html.twig', [
            'featured_formations' => $featuredFormations,
            'featured_services' => $featuredServices,
            'categories_with_count' => $categoriesWithCount,
            'stats' => [
                'total_formations' => $totalFormations,
                'total_categories' => $totalCategories,
                'years_experience' => 15, // Static value for EPROFOS experience
                'satisfied_clients' => 500, // Static value for marketing
            ],
        ]);
    }
}