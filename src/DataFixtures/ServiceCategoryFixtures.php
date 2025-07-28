<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Service\ServiceCategory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * ServiceCategory fixtures for EPROFOS platform.
 *
 * Creates service categories like Conseil, Formation, Certification, Accompagnement
 */
class ServiceCategoryFixtures extends Fixture
{
    public const SERVICE_CATEGORY_CONSEIL = 'service_category_conseil';

    public const SERVICE_CATEGORY_FORMATION = 'service_category_formation';

    public const SERVICE_CATEGORY_CERTIFICATION = 'service_category_certification';

    public const SERVICE_CATEGORY_ACCOMPAGNEMENT = 'service_category_accompagnement';

    public const SERVICE_CATEGORY_AUDIT = 'service_category_audit';

    private SluggerInterface $slugger;

    public function __construct(SluggerInterface $slugger)
    {
        $this->slugger = $slugger;
    }

    /**
     * Load service category fixtures.
     */
    public function load(ObjectManager $manager): void
    {
        $serviceCategories = [
            [
                'name' => 'Conseil et Expertise',
                'description' => 'Services de conseil personnalisés pour accompagner votre entreprise dans ses projets de transformation et d\'amélioration.',
                'reference' => self::SERVICE_CATEGORY_CONSEIL,
            ],
            [
                'name' => 'Formation Sur Mesure',
                'description' => 'Formations personnalisées et adaptées aux besoins spécifiques de votre organisation et de vos équipes.',
                'reference' => self::SERVICE_CATEGORY_FORMATION,
            ],
            [
                'name' => 'Certification et Validation',
                'description' => 'Services de certification professionnelle et validation des compétences acquises lors des formations.',
                'reference' => self::SERVICE_CATEGORY_CERTIFICATION,
            ],
            [
                'name' => 'Accompagnement et Coaching',
                'description' => 'Accompagnement personnalisé et coaching professionnel pour développer vos compétences et atteindre vos objectifs.',
                'reference' => self::SERVICE_CATEGORY_ACCOMPAGNEMENT,
            ],
            [
                'name' => 'Audit et Diagnostic',
                'description' => 'Services d\'audit et de diagnostic pour évaluer vos processus, compétences et identifier les axes d\'amélioration.',
                'reference' => self::SERVICE_CATEGORY_AUDIT,
            ],
        ];

        foreach ($serviceCategories as $categoryData) {
            $serviceCategory = new ServiceCategory();
            $serviceCategory->setName($categoryData['name']);
            $serviceCategory->setSlug($this->slugger->slug($categoryData['name'])->lower()->toString());
            $serviceCategory->setDescription($categoryData['description']);

            $manager->persist($serviceCategory);

            // Add reference for use in other fixtures
            $this->addReference($categoryData['reference'], $serviceCategory);
        }

        $manager->flush();
    }
}
