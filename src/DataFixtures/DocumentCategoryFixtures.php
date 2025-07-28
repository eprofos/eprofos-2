<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Document\DocumentCategory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Document Category Fixtures - Creates hierarchical categories for document organization.
 */
class DocumentCategoryFixtures extends Fixture
{
    public const LEGAL_CATEGORY_REFERENCE = 'document-category-legal';

    public const REGULATORY_CATEGORY_REFERENCE = 'document-category-regulatory';

    public const ACCESSIBILITY_CATEGORY_REFERENCE = 'document-category-accessibility';

    public const QUALITY_CATEGORY_REFERENCE = 'document-category-quality';

    public const TRAINING_CATEGORY_REFERENCE = 'document-category-training';

    public const INTERNAL_CATEGORY_REFERENCE = 'document-category-internal';

    public const STUDENT_CATEGORY_REFERENCE = 'document-category-student';

    public const ADMIN_CATEGORY_REFERENCE = 'document-category-admin';

    public function load(ObjectManager $manager): void
    {
        // Root categories
        $categories = [
            [
                'name' => 'Documents légaux',
                'slug' => 'documents-legaux',
                'description' => 'Ensemble des documents légaux et réglementaires de l\'organisme de formation',
                'icon' => 'fas fa-gavel',
                'color' => '#dc3545',
                'parent' => null,
                'level' => 0,
                'sortOrder' => 1,
                'reference' => self::LEGAL_CATEGORY_REFERENCE,
                'children' => [
                    [
                        'name' => 'Réglementation formation',
                        'slug' => 'documents-legaux/reglementation-formation',
                        'description' => 'Documents relatifs à la réglementation de la formation professionnelle',
                        'icon' => 'fas fa-book-open',
                        'color' => '#dc3545',
                        'sortOrder' => 1,
                        'reference' => self::REGULATORY_CATEGORY_REFERENCE,
                    ],
                    [
                        'name' => 'Accessibilité',
                        'slug' => 'documents-legaux/accessibilite',
                        'description' => 'Documents et politiques d\'accessibilité pour les personnes en situation de handicap',
                        'icon' => 'fas fa-universal-access',
                        'color' => '#20c997',
                        'sortOrder' => 2,
                        'reference' => self::ACCESSIBILITY_CATEGORY_REFERENCE,
                    ],
                ],
            ],
            [
                'name' => 'Système qualité',
                'slug' => 'systeme-qualite',
                'description' => 'Documents du système qualité et certification Qualiopi',
                'icon' => 'fas fa-certificate',
                'color' => '#ffc107',
                'parent' => null,
                'level' => 0,
                'sortOrder' => 2,
                'reference' => self::QUALITY_CATEGORY_REFERENCE,
                'children' => [
                    [
                        'name' => 'Procédures qualité',
                        'slug' => 'systeme-qualite/procedures',
                        'description' => 'Procédures du système de management de la qualité',
                        'icon' => 'fas fa-clipboard-check',
                        'color' => '#ffc107',
                        'sortOrder' => 1,
                        'reference' => 'document-category-quality-procedures',
                    ],
                    [
                        'name' => 'Indicateurs et tableaux de bord',
                        'slug' => 'systeme-qualite/indicateurs',
                        'description' => 'Indicateurs de performance et tableaux de bord qualité',
                        'icon' => 'fas fa-chart-line',
                        'color' => '#ffc107',
                        'sortOrder' => 2,
                        'reference' => 'document-category-quality-indicators',
                    ],
                ],
            ],
            [
                'name' => 'Formation et pédagogie',
                'slug' => 'formation-pedagogie',
                'description' => 'Documents pédagogiques et supports de formation',
                'icon' => 'fas fa-chalkboard-teacher',
                'color' => '#e83e8c',
                'parent' => null,
                'level' => 0,
                'sortOrder' => 3,
                'reference' => self::TRAINING_CATEGORY_REFERENCE,
                'children' => [
                    [
                        'name' => 'Supports pédagogiques',
                        'slug' => 'formation-pedagogie/supports',
                        'description' => 'Supports de cours et matériel pédagogique',
                        'icon' => 'fas fa-file-powerpoint',
                        'color' => '#e83e8c',
                        'sortOrder' => 1,
                        'reference' => 'document-category-training-materials',
                    ],
                    [
                        'name' => 'Évaluations',
                        'slug' => 'formation-pedagogie/evaluations',
                        'description' => 'Documents et grilles d\'évaluation',
                        'icon' => 'fas fa-tasks',
                        'color' => '#e83e8c',
                        'sortOrder' => 2,
                        'reference' => 'document-category-training-evaluations',
                    ],
                ],
            ],
            [
                'name' => 'Documents internes',
                'slug' => 'documents-internes',
                'description' => 'Politiques internes et procédures opérationnelles',
                'icon' => 'fas fa-building',
                'color' => '#6f42c1',
                'parent' => null,
                'level' => 0,
                'sortOrder' => 4,
                'reference' => self::INTERNAL_CATEGORY_REFERENCE,
                'children' => [
                    [
                        'name' => 'Ressources humaines',
                        'slug' => 'documents-internes/rh',
                        'description' => 'Politiques et procédures RH',
                        'icon' => 'fas fa-users',
                        'color' => '#6f42c1',
                        'sortOrder' => 1,
                        'reference' => 'document-category-internal-hr',
                    ],
                    [
                        'name' => 'Administration',
                        'slug' => 'documents-internes/administration',
                        'description' => 'Procédures administratives et organisationnelles',
                        'icon' => 'fas fa-cogs',
                        'color' => '#6f42c1',
                        'sortOrder' => 2,
                        'reference' => self::ADMIN_CATEGORY_REFERENCE,
                    ],
                ],
            ],
            [
                'name' => 'Documents étudiants',
                'slug' => 'documents-etudiants',
                'description' => 'Documents destinés aux étudiants et apprenants',
                'icon' => 'fas fa-graduation-cap',
                'color' => '#007bff',
                'parent' => null,
                'level' => 0,
                'sortOrder' => 5,
                'reference' => self::STUDENT_CATEGORY_REFERENCE,
                'children' => [
                    [
                        'name' => 'Livrets d\'accueil',
                        'slug' => 'documents-etudiants/livrets-accueil',
                        'description' => 'Livrets d\'accueil et guides d\'orientation',
                        'icon' => 'fas fa-book',
                        'color' => '#007bff',
                        'sortOrder' => 1,
                        'reference' => 'document-category-student-handbooks',
                    ],
                    [
                        'name' => 'Règlements',
                        'slug' => 'documents-etudiants/reglements',
                        'description' => 'Règlements intérieurs et codes de conduite',
                        'icon' => 'fas fa-balance-scale',
                        'color' => '#007bff',
                        'sortOrder' => 2,
                        'reference' => 'document-category-student-regulations',
                    ],
                    [
                        'name' => 'Conditions générales',
                        'slug' => 'documents-etudiants/conditions-generales',
                        'description' => 'Conditions générales de formation et d\'inscription',
                        'icon' => 'fas fa-handshake',
                        'color' => '#007bff',
                        'sortOrder' => 3,
                        'reference' => 'document-category-student-terms',
                    ],
                ],
            ],
        ];

        $this->createCategoriesRecursively($manager, $categories, null);

        $manager->flush();
    }

    private function createCategoriesRecursively(ObjectManager $manager, array $categories, ?DocumentCategory $parent = null): void
    {
        foreach ($categories as $categoryData) {
            $category = new DocumentCategory();
            $category->setName($categoryData['name'])
                ->setSlug($categoryData['slug'])
                ->setDescription($categoryData['description'])
                ->setIcon($categoryData['icon'])
                ->setColor($categoryData['color'])
                ->setParent($parent)
                ->setLevel($parent ? $parent->getLevel() + 1 : 0)
                ->setSortOrder($categoryData['sortOrder'])
                ->setIsActive(true)
            ;

            $manager->persist($category);
            $this->addReference($categoryData['reference'], $category);

            // Create children if they exist
            if (isset($categoryData['children']) && !empty($categoryData['children'])) {
                $this->createCategoriesRecursively($manager, $categoryData['children'], $category);
            }
        }
    }
}
