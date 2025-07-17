<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Main application fixtures for EPROFOS platform
 * 
 * This fixture serves as the main entry point for loading all application fixtures.
 * It ensures proper loading order through dependencies and provides a single
 * command to populate the entire database with realistic test data.
 * 
 * Usage: docker compose exec -it php php bin/console doctrine:fixtures:load
 */
class AppFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * Load main application fixtures
     * 
     * This method is intentionally empty as all data loading is handled
     * by the dependent fixtures. This class serves as an orchestrator
     * to ensure all fixtures are loaded in the correct order.
     */
    public function load(ObjectManager $manager): void
    {
        // All data loading is handled by dependent fixtures
        // This fixture serves as the main entry point and dependency orchestrator
        
        $manager->flush();
    }

    /**
     * Define fixture dependencies
     *
     * Ensures all fixtures are loaded in the correct order:
     * 1. Categories and ServiceCategories (no dependencies)
     * 2. Formations and Services (depend on categories)
     * 3. Modules (depend on formations)
     * 4. Chapters (depend on modules)
     * 5. Questionnaires (depend on formations)
     * 6. Questions (depend on questionnaires)
     * 7. QuestionOptions (depend on questions)
     * 8. QuestionnaireResponses (depend on questionnaires and options)
     * 9. Users (for prospect assignment)
     * 10. ProspectFixtures (independent prospects, depend on users)
     * 11. ContactRequests (depend on formations and services, will create prospects via service)
     * 12. SessionFixtures (depend on formations, will create prospects via service)
     * 13. NeedsAnalysisFixtures (depend on users and formations, will create prospects via service)
     * 14. LegalDocumentFixtures (no dependencies, required for Qualiopi compliance)
     */
    public function getDependencies(): array
    {
        return [
            CategoryFixtures::class,
            ServiceCategoryFixtures::class,
            FormationFixtures::class,
            ServiceFixtures::class,
            ModuleFixtures::class,
            ChapterFixtures::class,
            QuestionnaireFixtures::class,
            QuestionFixtures::class,
            QuestionOptionFixtures::class,
            QuestionnaireResponseFixtures::class,
            UserFixtures::class,
            ProspectFixtures::class,
            ContactRequestFixtures::class,
            SessionFixtures::class,
            NeedsAnalysisFixtures::class,
            LegalDocumentFixtures::class,
        ];
    }
}
