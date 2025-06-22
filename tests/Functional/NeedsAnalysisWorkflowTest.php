<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\NeedsAnalysisRequest;
use App\Entity\CompanyNeedsAnalysis;
use App\Entity\IndividualNeedsAnalysis;
use App\Entity\User;
use App\Repository\NeedsAnalysisRequestRepository;
use App\Service\NeedsAnalysisService;
use App\Service\TokenGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for the complete needs analysis workflow
 * 
 * Tests the entire process from request creation to completion,
 * including public forms, email notifications, and admin interface.
 */
class NeedsAnalysisWorkflowTest extends WebTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private ?NeedsAnalysisRequestRepository $requestRepository = null;
    private ?NeedsAnalysisService $needsAnalysisService = null;
    private ?TokenGeneratorService $tokenGenerator = null;
    private ?User $testUser = null;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset test user for each test
        $this->testUser = null;
        
        // Clean database before each test
        $this->cleanDatabase();
    }
    
    /**
     * Get container services for testing
     */
    private function getServices(): void
    {
        if (!$this->entityManager) {
            $container = static::getContainer();
            $this->entityManager = $container->get(EntityManagerInterface::class);
            $this->requestRepository = $container->get(NeedsAnalysisRequestRepository::class);
            $this->needsAnalysisService = $container->get(NeedsAnalysisService::class);
            $this->tokenGenerator = $container->get(TokenGeneratorService::class);
        }
    }

    /**
     * Test complete workflow for company needs analysis
     */
    public function testCompanyNeedsAnalysisWorkflow(): void
    {
        $client = static::createClient();
        $this->getServices();

        // Step 1: Create a company needs analysis request
        $request = $this->createCompanyRequest();
        $token = $request->getToken();

        // Step 2: Access the info page
        $client->request('GET', "/needs-analysis/info/{$token}");
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Analyse des besoins de formation');

        // Step 3: Access the company form
        $crawler = $client->request('GET', "/needs-analysis/form/{$token}");
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="company_needs_analysis"]');

        // Debug: Log the page content to see what's actually rendered
        error_log("=== COMPANY FORM PAGE DEBUG ===");
        error_log("Page title: " . $crawler->filter('title')->text());
        
        // Look for any submit buttons
        $allButtons = $crawler->filter('button[type="submit"]');
        error_log("Found " . $allButtons->count() . " submit buttons");
        
        $allButtons->each(function ($node, $i) {
            error_log("Button $i content: '" . trim($node->text()) . "'");
            error_log("Button $i HTML: " . $node->outerHtml());
        });
        
        // Look for forms
        $forms = $crawler->filter('form');
        error_log("Found " . $forms->count() . " forms");
        
        $forms->each(function ($node, $i) {
            error_log("Form $i name: " . $node->attr('name'));
        });

        // Step 4: Submit the company form
        $formData = $this->getCompanyFormData();
        $client->submitForm('Envoyer l\'analyse des besoins', $formData);
        
        // Should redirect to success page
        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertSelectorTextContains('h1', 'Formulaire envoyé avec succès !');

        // Step 5: Verify the analysis was saved
        $this->entityManager->refresh($request);
        $this->assertEquals('completed', $request->getStatus());
        $this->assertInstanceOf(CompanyNeedsAnalysis::class, $request->getCompanyAnalysis());

        // Step 6: Verify accessing the form again shows completed page
        $client->request('GET', "/needs-analysis/form/{$token}");
        $this->assertSelectorTextContains('h1', 'Formulaire déjà complété');
    }

    /**
     * Test complete workflow for individual needs analysis
     */
    public function testIndividualNeedsAnalysisWorkflow(): void
    {
        $client = static::createClient();
        $this->getServices();

        // Step 1: Create an individual needs analysis request
        $request = $this->createIndividualRequest();
        $token = $request->getToken();

        // Step 2: Access the individual form
        $client->request('GET', "/needs-analysis/form/{$token}");
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="individual_needs_analysis"]');

        // Step 3: Submit the individual form
        $formData = $this->getIndividualFormData();
        $client->submitForm('Envoyer l\'analyse des besoins', $formData);
        
        // Should redirect to success page
        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertSelectorTextContains('h1', 'Formulaire envoyé avec succès !');

        // Step 4: Verify the analysis was saved
        $this->entityManager->refresh($request);
        $this->assertEquals('completed', $request->getStatus());
        $this->assertInstanceOf(IndividualNeedsAnalysis::class, $request->getIndividualAnalysis());
    }

    /**
     * Test expired token handling
     */
    public function testExpiredTokenHandling(): void
    {
        $client = static::createClient();
        $this->getServices();

        // Create an expired request
        $request = $this->createExpiredRequest();
        $token = $request->getToken();

        // Try to access the form
        $client->request('GET', "/needs-analysis/form/{$token}");
        $this->assertSelectorTextContains('h1', 'Lien expiré');
    }

    /**
     * Test invalid token handling
     */
    public function testInvalidTokenHandling(): void
    {
        $client = static::createClient();

        // Try to access with invalid token
        $invalidToken = 'invalid-token-123';
        $client->request('GET', "/needs-analysis/form/{$invalidToken}");
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * Test admin interface for viewing completed analysis
     */
    public function testAdminViewCompletedAnalysis(): void
    {
        $client = static::createClient();
        $this->getServices();

        // Create and complete a company analysis
        $request = $this->createCompanyRequest();
        $analysis = $this->createCompanyAnalysis($request);
        $request->setCompanyAnalysis($analysis);
        $request->setStatus('completed');
        $this->entityManager->flush();

        // Authenticate as admin user
        $adminUser = $this->createTestUser();
        $client->loginUser($adminUser);

        // Access admin show page
        $client->request('GET', "/admin/needs-analysis/{$request->getId()}");
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.card-title', 'Analyse des besoins');
    }

    /**
     * Test form validation errors
     */
    public function testFormValidationErrors(): void
    {
        $client = static::createClient();
        $this->getServices();

        $request = $this->createCompanyRequest();
        $token = $request->getToken();

        // Submit empty form
        $client->request('GET', "/needs-analysis/form/{$token}");
        $client->submitForm('Envoyer l\'analyse des besoins', []);

        // Should show validation errors
        $this->assertSelectorExists('.is-invalid');
        $this->assertSelectorTextContains('.invalid-feedback', 'Cette valeur ne doit pas être vide');
    }

    /**
     * Create a test user for needs analysis requests (singleton pattern)
     */
    private function createTestUser(): User
    {
        if ($this->testUser === null) {
            $this->testUser = new User();
            $this->testUser->setEmail('test@eprofos.com');
            $this->testUser->setFirstName('Test');
            $this->testUser->setLastName('Admin');
            $this->testUser->setPassword('$2y$13$test.password.hash'); // Dummy hash for testing
            $this->testUser->setRoles(['ROLE_ADMIN']);
            $this->testUser->setIsActive(true);

            $this->entityManager->persist($this->testUser);
            $this->entityManager->flush();
        }

        return $this->testUser;
    }

    /**
     * Create a company needs analysis request for testing
     */
    private function createCompanyRequest(): NeedsAnalysisRequest
    {
        $user = $this->createTestUser();
        
        $request = new NeedsAnalysisRequest();
        $request->setType('company');
        $request->setRecipientEmail('test@company.com');
        $request->setRecipientName('Test Company');
        $request->setCompanyName('Test Company Ltd');
        $request->setToken($this->tokenGenerator->generateToken());
        $request->setStatus('sent');
        $request->setExpiresAt(new \DateTimeImmutable('+30 days'));
        $request->setSentAt(new \DateTimeImmutable());
        $request->setCreatedByUser($user);

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        return $request;
    }

    /**
     * Create an individual needs analysis request for testing
     */
    private function createIndividualRequest(): NeedsAnalysisRequest
    {
        $user = $this->createTestUser();
        
        $request = new NeedsAnalysisRequest();
        $request->setType('individual');
        $request->setRecipientEmail('test@individual.com');
        $request->setRecipientName('Test Individual');
        $request->setToken($this->tokenGenerator->generateToken());
        $request->setStatus('sent');
        $request->setExpiresAt(new \DateTimeImmutable('+30 days'));
        $request->setSentAt(new \DateTimeImmutable());
        $request->setCreatedByUser($user);

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        return $request;
    }

    /**
     * Create an expired request for testing
     */
    private function createExpiredRequest(): NeedsAnalysisRequest
    {
        $user = $this->createTestUser();
        
        $request = new NeedsAnalysisRequest();
        $request->setType('company');
        $request->setRecipientEmail('test@expired.com');
        $request->setRecipientName('Test Expired');
        $request->setToken($this->tokenGenerator->generateToken());
        $request->setStatus('expired');
        $request->setExpiresAt(new \DateTimeImmutable('-1 day'));
        $request->setSentAt(new \DateTimeImmutable('-31 days'));
        $request->setCreatedByUser($user);

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        return $request;
    }

    /**
     * Create a company analysis for testing
     */
    private function createCompanyAnalysis(NeedsAnalysisRequest $request): CompanyNeedsAnalysis
    {
        $analysis = new CompanyNeedsAnalysis();
        $analysis->setNeedsAnalysisRequest($request);
        $analysis->setCompanyName('Test Company Ltd');
        $analysis->setResponsiblePerson('John Doe');
        $analysis->setContactEmail('john@testcompany.com');
        $analysis->setContactPhone('0123456789');
        $analysis->setCompanyAddress('123 Test Street, Test City, 12345');
        $analysis->setActivitySector('Technology');
        $analysis->setEmployeeCount(75);
        $analysis->setTraineesInfo([
            ['first_name' => 'Alice', 'last_name' => 'Smith', 'position' => 'Developer'],
            ['first_name' => 'Bob', 'last_name' => 'Johnson', 'position' => 'Team Lead']
        ]);
        $analysis->setTrainingTitle('Advanced Web Development');
        $analysis->setTrainingDurationHours(40);
        $analysis->setTrainingLocationPreference('hybrid');
        $analysis->setTrainingExpectations('Improve team technical skills and productivity');
        $analysis->setSpecificNeeds('Focus on modern frameworks and best practices');

        $this->entityManager->persist($analysis);
        $this->entityManager->flush();

        return $analysis;
    }

    /**
     * Get sample form data for company analysis
     */
    private function getCompanyFormData(): array
    {
        return [
            'company_needs_analysis[company_name]' => 'Test Company Ltd',
            'company_needs_analysis[responsible_person]' => 'John Doe',
            'company_needs_analysis[contact_email]' => 'john@testcompany.com',
            'company_needs_analysis[contact_phone]' => '0123456789',
            'company_needs_analysis[company_address]' => '123 Test Street, Test City, 12345',
            'company_needs_analysis[activity_sector]' => 'Technology',
            'company_needs_analysis[employee_count]' => '75',
            'company_needs_analysis[training_title]' => 'Advanced Web Development',
            'company_needs_analysis[training_duration_hours]' => '40',
            'company_needs_analysis[training_location_preference]' => 'hybrid',
            'company_needs_analysis[training_expectations]' => 'Improve team technical skills and productivity',
            'company_needs_analysis[specific_needs]' => 'Focus on modern frameworks and best practices',
        ];
    }

    /**
     * Get sample form data for individual analysis
     */
    private function getIndividualFormData(): array
    {
        return [
            'individual_needs_analysis[first_name]' => 'John',
            'individual_needs_analysis[last_name]' => 'Doe',
            'individual_needs_analysis[address]' => '123 Main Street, Test City, 12345',
            'individual_needs_analysis[phone]' => '0123456789',
            'individual_needs_analysis[email]' => 'john.doe@example.com',
            'individual_needs_analysis[status]' => 'employee',
            'individual_needs_analysis[funding_type]' => 'cpf',
            'individual_needs_analysis[desired_training_title]' => 'Advanced Web Development',
            'individual_needs_analysis[professional_objective]' => 'Become a senior full-stack developer and lead technical projects',
            'individual_needs_analysis[current_level]' => 'intermediate',
            'individual_needs_analysis[desired_duration_hours]' => '40',
            'individual_needs_analysis[training_location_preference]' => 'hybrid',
            'individual_needs_analysis[training_expectations]' => 'Learn modern frameworks and improve technical leadership skills',
            'individual_needs_analysis[specific_needs]' => 'Focus on practical projects and real-world applications',
        ];
    }

    /**
     * Clean the database before each test
     */
    private function cleanDatabase(): void
    {
        if (!$this->entityManager) {
            return; // Skip cleaning if no entity manager yet
        }
        
        // Remove all test data in correct order (respecting foreign key constraints)
        $this->entityManager->createQuery('DELETE FROM App\Entity\CompanyNeedsAnalysis')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\IndividualNeedsAnalysis')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\NeedsAnalysisRequest')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\User')->execute();
        
        $this->entityManager->clear();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cleanDatabase();
    }
}