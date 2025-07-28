<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Training\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Category fixtures for EPROFOS platform.
 *
 * Creates realistic categories for professional training courses
 * including IT, Management, Languages, Accounting, Marketing, etc.
 */
class CategoryFixtures extends Fixture
{
    public const CATEGORY_INFORMATIQUE = 'category_informatique';

    public const CATEGORY_MANAGEMENT = 'category_management';

    public const CATEGORY_LANGUES = 'category_langues';

    public const CATEGORY_COMPTABILITE = 'category_comptabilite';

    public const CATEGORY_MARKETING = 'category_marketing';

    public const CATEGORY_RESSOURCES_HUMAINES = 'category_ressources_humaines';

    public const CATEGORY_QUALITE = 'category_qualite';

    public const CATEGORY_SECURITE = 'category_securite';

    private SluggerInterface $slugger;

    public function __construct(SluggerInterface $slugger)
    {
        $this->slugger = $slugger;
    }

    /**
     * Load category fixtures.
     */
    public function load(ObjectManager $manager): void
    {
        $categories = [
            [
                'name' => 'Informatique et Numérique',
                'description' => 'Formations spécialisées dans les technologies de l\'information, développement web, cybersécurité, bureautique et transformation digitale.',
                'icon' => 'fas fa-laptop-code',
                'reference' => self::CATEGORY_INFORMATIQUE,
                'isActive' => true,
            ],
            [
                'name' => 'Management et Leadership',
                'description' => 'Développez vos compétences managériales et de leadership pour diriger efficacement vos équipes et projets.',
                'icon' => 'fas fa-users',
                'reference' => self::CATEGORY_MANAGEMENT,
                'isActive' => true,
            ],
            [
                'name' => 'Langues Étrangères',
                'description' => 'Formations linguistiques professionnelles pour améliorer votre communication internationale et vos opportunités de carrière.',
                'icon' => 'fas fa-globe',
                'reference' => self::CATEGORY_LANGUES,
                'isActive' => true,
            ],
            [
                'name' => 'Comptabilité et Finance',
                'description' => 'Maîtrisez les aspects financiers et comptables de l\'entreprise, de la gestion budgétaire à l\'analyse financière.',
                'icon' => 'fas fa-calculator',
                'reference' => self::CATEGORY_COMPTABILITE,
                'isActive' => true,
            ],
            [
                'name' => 'Marketing et Communication',
                'description' => 'Stratégies marketing modernes, communication digitale, réseaux sociaux et techniques de vente pour développer votre business.',
                'icon' => 'fas fa-bullhorn',
                'reference' => self::CATEGORY_MARKETING,
                'isActive' => true,
            ],
            [
                'name' => 'Ressources Humaines',
                'description' => 'Gestion des talents, recrutement, droit du travail et développement des compétences pour optimiser le capital humain.',
                'icon' => 'fas fa-user-tie',
                'reference' => self::CATEGORY_RESSOURCES_HUMAINES,
                'isActive' => true,
            ],
            [
                'name' => 'Qualité et Amélioration Continue',
                'description' => 'Méthodes et outils pour l\'amélioration continue, certification qualité et optimisation des processus.',
                'icon' => 'fas fa-award',
                'reference' => self::CATEGORY_QUALITE,
                'isActive' => true,
            ],
            [
                'name' => 'Sécurité et Prévention',
                'description' => 'Formation aux normes de sécurité, prévention des risques professionnels et gestion de la sécurité au travail.',
                'icon' => 'fas fa-shield-alt',
                'reference' => self::CATEGORY_SECURITE,
                'isActive' => false, // Temporarily inactive for testing
            ],
        ];

        foreach ($categories as $categoryData) {
            $category = new Category();
            $category->setName($categoryData['name']);
            $category->setSlug($this->slugger->slug($categoryData['name'])->lower()->toString());
            $category->setDescription($categoryData['description']);
            $category->setIcon($categoryData['icon']);
            $category->setIsActive($categoryData['isActive']);

            $manager->persist($category);

            // Add reference for use in other fixtures
            $this->addReference($categoryData['reference'], $category);
        }

        $manager->flush();
    }
}
