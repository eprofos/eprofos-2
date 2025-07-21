<?php

namespace App\DataFixtures;

use App\Entity\User\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * User fixtures for creating default admin user
 * 
 * Creates a default admin user for accessing the admin interface.
 * This fixture should be run after installation to set up initial access.
 */
class UserFixtures extends Fixture
{
    public const ADMIN_USER_REFERENCE = 'admin-user';

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    /**
     * Load default admin user
     */
    public function load(ObjectManager $manager): void
    {
        // Create default admin user
        $admin = new User();
        $admin->setEmail('admin@eprofos.fr');
        $admin->setFirstName('Admin');
        $admin->setLastName('EPROFOS');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setIsActive(true);
        
        // Hash the password
        $hashedPassword = $this->passwordHasher->hashPassword(
            $admin,
            'admin123' // Default password - should be changed after first login
        );
        $admin->setPassword($hashedPassword);
        
        $manager->persist($admin);
        
        // Add reference for other fixtures
        $this->addReference(self::ADMIN_USER_REFERENCE, $admin);

        // Create a second admin user for testing
        $testAdmin = new User();
        $testAdmin->setEmail('test@eprofos.fr');
        $testAdmin->setFirstName('Test');
        $testAdmin->setLastName('Administrator');
        $testAdmin->setRoles(['ROLE_ADMIN']);
        $testAdmin->setIsActive(true);
        
        $hashedTestPassword = $this->passwordHasher->hashPassword(
            $testAdmin,
            'test123'
        );
        $testAdmin->setPassword($hashedTestPassword);
        
        $manager->persist($testAdmin);

        $manager->flush();
    }
}