<?php

namespace App\Tests\Service;

use App\Entity\Formation;
use App\Entity\Module;
use App\Entity\Chapter;
use App\Entity\Course;
use App\Entity\Exercise;
use App\Entity\QCM;
use App\Entity\Category;
use App\Service\DurationCalculationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Test class for DurationCalculationService
 */
class DurationCalculationServiceTest extends TestCase
{
    private DurationCalculationService $service;
    private MockObject&EntityManagerInterface $entityManager;
    private MockObject&CacheInterface $cache;
    private MockObject&LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new DurationCalculationService(
            $this->entityManager,
            $this->cache,
            $this->logger
        );
    }

    public function testCalculateCourseDuration(): void
    {
        // Create a course with exercises and QCMs
        $course = new Course();
        $course->setTitle('Test Course');
        $course->setSlug('test-course');
        $course->setDescription('Test Description');
        $course->setType(Course::TYPE_LESSON);
        $course->setDurationMinutes(120); // 2 hours base duration
        $course->setOrderIndex(1);

        // Add exercises
        $exercise1 = new Exercise();
        $exercise1->setTitle('Exercise 1');
        $exercise1->setSlug('exercise-1');
        $exercise1->setDescription('Test Exercise 1');
        $exercise1->setInstructions('Complete this exercise');
        $exercise1->setType(Exercise::TYPE_PRACTICAL);
        $exercise1->setDifficulty(Exercise::DIFFICULTY_BEGINNER);
        $exercise1->setEstimatedDurationMinutes(30);
        $exercise1->setOrderIndex(1);
        $exercise1->setIsActive(true);
        $exercise1->setCourse($course);

        $exercise2 = new Exercise();
        $exercise2->setTitle('Exercise 2');
        $exercise2->setSlug('exercise-2');
        $exercise2->setDescription('Test Exercise 2');
        $exercise2->setInstructions('Complete this exercise');
        $exercise2->setType(Exercise::TYPE_THEORETICAL);
        $exercise2->setDifficulty(Exercise::DIFFICULTY_INTERMEDIATE);
        $exercise2->setEstimatedDurationMinutes(45);
        $exercise2->setOrderIndex(2);
        $exercise2->setIsActive(true);
        $exercise2->setCourse($course);

        // Add QCM
        $qcm = new QCM();
        $qcm->setTitle('Test QCM');
        $qcm->setSlug('test-qcm');
        $qcm->setDescription('Test QCM Description');
        $qcm->setQuestions([
            [
                'question' => 'What is 2+2?',
                'answers' => ['3', '4', '5'],
                'correct' => 1,
                'explanation' => '2+2 equals 4'
            ]
        ]);
        $qcm->setTimeLimitMinutes(15);
        $qcm->setMaxScore(100);
        $qcm->setPassingScore(70);
        $qcm->setOrderIndex(1);
        $qcm->setIsActive(true);
        $qcm->setCourse($course);

        $course->addExercise($exercise1);
        $course->addExercise($exercise2);
        $course->addQcm($qcm);

        // Mock cache to return calculated value
        $this->cache->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createMock(ItemInterface::class);
                return $callback($item);
            });

        // Test calculation
        $totalDuration = $this->service->calculateCourseDuration($course);

        // Expected: 120 (base) + 30 (exercise1) + 45 (exercise2) + 15 (qcm) = 210 minutes
        $this->assertEquals(210, $totalDuration);
    }

    public function testCalculateChapterDuration(): void
    {
        // Create a chapter with courses
        $chapter = new Chapter();
        $chapter->setTitle('Test Chapter');
        $chapter->setSlug('test-chapter');
        $chapter->setDescription('Test Chapter Description');
        $chapter->setDurationMinutes(0); // Will be calculated
        $chapter->setOrderIndex(1);

        // Create courses
        $course1 = new Course();
        $course1->setTitle('Course 1');
        $course1->setSlug('course-1');
        $course1->setDescription('Course 1 Description');
        $course1->setType(Course::TYPE_LESSON);
        $course1->setDurationMinutes(60);
        $course1->setOrderIndex(1);
        $course1->setIsActive(true);
        $course1->setChapter($chapter);

        $course2 = new Course();
        $course2->setTitle('Course 2');
        $course2->setSlug('course-2');
        $course2->setDescription('Course 2 Description');
        $course2->setType(Course::TYPE_VIDEO);
        $course2->setDurationMinutes(90);
        $course2->setOrderIndex(2);
        $course2->setIsActive(true);
        $course2->setChapter($chapter);

        $chapter->addCourse($course1);
        $chapter->addCourse($course2);

        // Mock cache to return calculated value
        $this->cache->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createMock(ItemInterface::class);
                return $callback($item);
            });

        // Mock the course duration calculations
        $this->cache->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createMock(ItemInterface::class);
                return $callback($item);
            });

        // Test calculation
        $totalDuration = $this->service->calculateChapterDuration($chapter);

        // Expected: 60 (course1) + 90 (course2) = 150 minutes
        $this->assertEquals(150, $totalDuration);
    }

    public function testCalculateModuleDuration(): void
    {
        // Create a module with chapters
        $module = new Module();
        $module->setTitle('Test Module');
        $module->setSlug('test-module');
        $module->setDescription('Test Module Description');
        $module->setDurationHours(0); // Will be calculated
        $module->setOrderIndex(1);

        // Create chapters with courses
        $chapter1 = new Chapter();
        $chapter1->setTitle('Chapter 1');
        $chapter1->setSlug('chapter-1');
        $chapter1->setDescription('Chapter 1 Description');
        $chapter1->setDurationMinutes(120); // 2 hours
        $chapter1->setOrderIndex(1);
        $chapter1->setIsActive(true);
        $chapter1->setModule($module);

        // Add courses to chapter1
        $course1 = new Course();
        $course1->setTitle('Course 1');
        $course1->setSlug('course-1');
        $course1->setDescription('Course 1 Description');
        $course1->setType(Course::TYPE_LESSON);
        $course1->setDurationMinutes(60);
        $course1->setOrderIndex(1);
        $course1->setIsActive(true);
        $course1->setChapter($chapter1);

        $course2 = new Course();
        $course2->setTitle('Course 2');
        $course2->setSlug('course-2');
        $course2->setDescription('Course 2 Description');
        $course2->setType(Course::TYPE_VIDEO);
        $course2->setDurationMinutes(60);
        $course2->setOrderIndex(2);
        $course2->setIsActive(true);
        $course2->setChapter($chapter1);

        $chapter1->addCourse($course1);
        $chapter1->addCourse($course2);

        $chapter2 = new Chapter();
        $chapter2->setTitle('Chapter 2');
        $chapter2->setSlug('chapter-2');
        $chapter2->setDescription('Chapter 2 Description');
        $chapter2->setDurationMinutes(180); // 3 hours
        $chapter2->setOrderIndex(2);
        $chapter2->setIsActive(true);
        $chapter2->setModule($module);

        // Add courses to chapter2
        $course3 = new Course();
        $course3->setTitle('Course 3');
        $course3->setSlug('course-3');
        $course3->setDescription('Course 3 Description');
        $course3->setType(Course::TYPE_PRACTICAL);
        $course3->setDurationMinutes(120);
        $course3->setOrderIndex(1);
        $course3->setIsActive(true);
        $course3->setChapter($chapter2);

        $course4 = new Course();
        $course4->setTitle('Course 4');
        $course4->setSlug('course-4');
        $course4->setDescription('Course 4 Description');
        $course4->setType(Course::TYPE_DOCUMENT);
        $course4->setDurationMinutes(60);
        $course4->setOrderIndex(2);
        $course4->setIsActive(true);
        $course4->setChapter($chapter2);

        $chapter2->addCourse($course3);
        $chapter2->addCourse($course4);

        $module->addChapter($chapter1);
        $module->addChapter($chapter2);

        // Mock cache to return calculated value
        $this->cache->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createMock(ItemInterface::class);
                return $callback($item);
            });

        // Test calculation
        $totalDuration = $this->service->calculateModuleDuration($module);

        // Expected: (60+60) + (120+60) = 300 minutes = 5 hours
        $this->assertEquals(5, $totalDuration);
    }

    public function testCalculateFormationDuration(): void
    {
        // Create a formation with modules
        $category = new Category();
        $category->setName('Test Category');
        $category->setSlug('test-category');
        $category->setDescription('Test Category Description');

        $formation = new Formation();
        $formation->setTitle('Test Formation');
        $formation->setSlug('test-formation');
        $formation->setDescription('Test Formation Description');
        $formation->setDurationHours(0); // Will be calculated
        $formation->setPrice('1000.00');
        $formation->setLevel('beginner');
        $formation->setFormat('in-person');
        $formation->setCategory($category);

        // Create modules with chapters and courses
        $module1 = new Module();
        $module1->setTitle('Module 1');
        $module1->setSlug('module-1');
        $module1->setDescription('Module 1 Description');
        $module1->setDurationHours(8); // 1 day
        $module1->setOrderIndex(1);
        $module1->setIsActive(true);
        $module1->setFormation($formation);

        // Add chapter to module1
        $chapter1 = new Chapter();
        $chapter1->setTitle('Chapter 1');
        $chapter1->setSlug('chapter-1');
        $chapter1->setDescription('Chapter 1 Description');
        $chapter1->setDurationMinutes(480); // 8 hours
        $chapter1->setOrderIndex(1);
        $chapter1->setIsActive(true);
        $chapter1->setModule($module1);

        // Add course to chapter1
        $course1 = new Course();
        $course1->setTitle('Course 1');
        $course1->setSlug('course-1');
        $course1->setDescription('Course 1 Description');
        $course1->setType(Course::TYPE_LESSON);
        $course1->setDurationMinutes(480); // 8 hours
        $course1->setOrderIndex(1);
        $course1->setIsActive(true);
        $course1->setChapter($chapter1);

        $chapter1->addCourse($course1);
        $module1->addChapter($chapter1);

        $module2 = new Module();
        $module2->setTitle('Module 2');
        $module2->setSlug('module-2');
        $module2->setDescription('Module 2 Description');
        $module2->setDurationHours(16); // 2 days
        $module2->setOrderIndex(2);
        $module2->setIsActive(true);
        $module2->setFormation($formation);

        // Add chapter to module2
        $chapter2 = new Chapter();
        $chapter2->setTitle('Chapter 2');
        $chapter2->setSlug('chapter-2');
        $chapter2->setDescription('Chapter 2 Description');
        $chapter2->setDurationMinutes(960); // 16 hours
        $chapter2->setOrderIndex(1);
        $chapter2->setIsActive(true);
        $chapter2->setModule($module2);

        // Add course to chapter2
        $course2 = new Course();
        $course2->setTitle('Course 2');
        $course2->setSlug('course-2');
        $course2->setDescription('Course 2 Description');
        $course2->setType(Course::TYPE_VIDEO);
        $course2->setDurationMinutes(960); // 16 hours
        $course2->setOrderIndex(1);
        $course2->setIsActive(true);
        $course2->setChapter($chapter2);

        $chapter2->addCourse($course2);
        $module2->addChapter($chapter2);

        $formation->addModule($module1);
        $formation->addModule($module2);

        // Mock cache to return calculated value
        $this->cache->method('get')
            ->willReturnCallback(function ($key, $callback) {
                $item = $this->createMock(ItemInterface::class);
                return $callback($item);
            });

        // Test calculation
        $totalDuration = $this->service->calculateFormationDuration($formation);

        // Expected: 8 + 16 = 24 hours
        $this->assertEquals(24, $totalDuration);
    }

    public function testMinutesToHours(): void
    {
        // Test rounding up (default)
        $this->assertEquals(2, $this->service->minutesToHours(61));
        $this->assertEquals(1, $this->service->minutesToHours(60));
        $this->assertEquals(1, $this->service->minutesToHours(1));

        // Test rounding normal
        $this->assertEquals(1, $this->service->minutesToHours(61, false));
        $this->assertEquals(1, $this->service->minutesToHours(60, false));
        $this->assertEquals(0, $this->service->minutesToHours(1, false));
    }

    public function testHoursToMinutes(): void
    {
        $this->assertEquals(60, $this->service->hoursToMinutes(1));
        $this->assertEquals(120, $this->service->hoursToMinutes(2));
        $this->assertEquals(0, $this->service->hoursToMinutes(0));
    }

    public function testFormatDuration(): void
    {
        // Test minutes formatting
        $this->assertEquals('30 min', $this->service->formatDuration(30, 'minutes'));
        $this->assertEquals('1h', $this->service->formatDuration(60, 'minutes'));
        $this->assertEquals('1h 30min', $this->service->formatDuration(90, 'minutes'));

        // Test hours formatting
        $this->assertEquals('7h', $this->service->formatDuration(7, 'hours'));
        $this->assertEquals('1 jour', $this->service->formatDuration(8, 'hours'));
        $this->assertEquals('1 jour 2h', $this->service->formatDuration(10, 'hours'));
        $this->assertEquals('2 jours', $this->service->formatDuration(16, 'hours'));
    }
}
