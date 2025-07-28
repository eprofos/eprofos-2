<?php

declare(strict_types=1);

namespace App\Tests\DataFixtures;

use App\DataFixtures\TeacherFixtures;
use App\Entity\User\Teacher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Test class for TeacherFixtures.
 *
 * Verifies that teacher fixtures are created correctly with proper data validation.
 */
class TeacherFixturesTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    /**
     * Test that teacher fixtures can be loaded successfully.
     */
    public function testTeacherFixturesLoad(): void
    {
        $fixtures = new TeacherFixtures($this->passwordHasher);

        // This should not throw any exceptions
        $fixtures->load($this->entityManager);

        // Verify that teachers were created
        $teacherRepository = $this->entityManager->getRepository(Teacher::class);
        $teachers = $teacherRepository->findAll();

        $this->assertGreaterThan(0, count($teachers));
    }

    /**
     * Test that main test teacher is created with correct data.
     */
    public function testMainTestTeacherCreation(): void
    {
        $teacherRepository = $this->entityManager->getRepository(Teacher::class);
        $teacher = $teacherRepository->findByEmail('teacher@eprofos.com');

        $this->assertNotNull($teacher);
        $this->assertEquals('Marie', $teacher->getFirstName());
        $this->assertEquals('Dubois', $teacher->getLastName());
        $this->assertEquals('Dr', $teacher->getTitle());
        $this->assertEquals('Informatique et Digital', $teacher->getSpecialty());
        $this->assertEquals(15, $teacher->getYearsOfExperience());
        $this->assertTrue($teacher->isEmailVerified());
        $this->assertTrue($teacher->isActive());
        $this->assertContains('ROLE_TEACHER', $teacher->getRoles());
    }

    /**
     * Test that specialized teachers are created correctly.
     */
    public function testSpecializedTeachersCreation(): void
    {
        $teacherRepository = $this->entityManager->getRepository(Teacher::class);

        // Test IT teacher
        $itTeacher = $teacherRepository->findByEmail('formateur.it@eprofos.com');
        $this->assertNotNull($itTeacher);
        $this->assertEquals('Thomas', $itTeacher->getFirstName());
        $this->assertEquals('Informatique et Digital', $itTeacher->getSpecialty());

        // Test Management teacher
        $managementTeacher = $teacherRepository->findByEmail('formatrice.management@eprofos.com');
        $this->assertNotNull($managementTeacher);
        $this->assertEquals('Sophie', $managementTeacher->getFirstName());
        $this->assertEquals('Management et Leadership', $managementTeacher->getSpecialty());

        // Test HR teacher
        $hrTeacher = $teacherRepository->findByEmail('formateur.rh@eprofos.com');
        $this->assertNotNull($hrTeacher);
        $this->assertEquals('Philippe', $hrTeacher->getFirstName());
        $this->assertEquals('Ressources Humaines', $hrTeacher->getSpecialty());
    }

    /**
     * Test that passwords are properly hashed.
     */
    public function testPasswordHashing(): void
    {
        $teacherRepository = $this->entityManager->getRepository(Teacher::class);
        $teacher = $teacherRepository->findByEmail('teacher@eprofos.com');

        $this->assertNotNull($teacher);
        $this->assertNotNull($teacher->getPassword());
        $this->assertNotEquals('password', $teacher->getPassword()); // Should be hashed

        // Verify password can be validated
        $isValid = $this->passwordHasher->isPasswordValid($teacher, 'password');
        $this->assertTrue($isValid);
    }

    /**
     * Test that different teacher statuses are created.
     */
    public function testTeacherStatusVariety(): void
    {
        $teacherRepository = $this->entityManager->getRepository(Teacher::class);
        $allTeachers = $teacherRepository->findAll();

        // Should have some verified and unverified teachers
        $verifiedTeachers = array_filter($allTeachers, static fn ($t) => $t->isEmailVerified());
        $unverifiedTeachers = array_filter($allTeachers, static fn ($t) => !$t->isEmailVerified());

        $this->assertGreaterThan(0, count($verifiedTeachers));
        $this->assertGreaterThan(0, count($unverifiedTeachers));

        // Should have some active and inactive teachers
        $activeTeachers = array_filter($allTeachers, static fn ($t) => $t->isActive());
        $inactiveTeachers = array_filter($allTeachers, static fn ($t) => !$t->isActive());

        $this->assertGreaterThan(0, count($activeTeachers));
        $this->assertGreaterThan(0, count($inactiveTeachers));
    }

    /**
     * Test that teacher specialties are varied.
     */
    public function testTeacherSpecialtyVariety(): void
    {
        $teacherRepository = $this->entityManager->getRepository(Teacher::class);
        $allTeachers = $teacherRepository->findAll();

        $specialties = array_unique(array_map(static fn ($t) => $t->getSpecialty(), $allTeachers));

        // Should have multiple different specialties
        $this->assertGreaterThan(5, count($specialties));

        // Should include some expected specialties
        $this->assertContains('Informatique et Digital', $specialties);
        $this->assertContains('Management et Leadership', $specialties);
        $this->assertContains('Ressources Humaines', $specialties);
    }

    /**
     * Test that experience levels are realistic.
     */
    public function testExperienceLevels(): void
    {
        $teacherRepository = $this->entityManager->getRepository(Teacher::class);
        $allTeachers = $teacherRepository->findAll();

        $experiences = array_map(static fn ($t) => $t->getYearsOfExperience(), $allTeachers);
        $experiences = array_filter($experiences, static fn ($e) => $e !== null);

        $this->assertGreaterThan(0, count($experiences));

        // Should have varied experience levels
        $minExperience = min($experiences);
        $maxExperience = max($experiences);

        $this->assertGreaterThanOrEqual(1, $minExperience);
        $this->assertLessThanOrEqual(30, $maxExperience);
        $this->assertGreaterThan($minExperience, $maxExperience); // Should have variety
    }
}
