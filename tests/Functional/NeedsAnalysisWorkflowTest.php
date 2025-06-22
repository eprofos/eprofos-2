<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\NeedsAnalysisRequest;
use App\Entity\CompanyNeedsAnalysis;
use App\Entity\IndividualNeedsAnalysis;
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
    private EntityManagerInterface $entityManager;
    private NeedsAnalysisRequestRepository $requestRepository;
    private NeedsAnalysisService $needsAnalysisService;
    private TokenGeneratorService $tokenGenerator;

    protected function setUp(): void
    {
        parent::setUp();
        
        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->requestRepository = $container->get(NeedsAnalysisRequestRepository::class);
        $this->needsAnalysisService = $container->get(NeedsAnalysisService::class);
        $this->tokenGenerator = $container->get(TokenGeneratorService::class);
        
        // Clean database before each test
        $this->cleanDatabase();
    }

    /**
     * Test complete workflow for company needs analysis
     */
    public function testCompanyNeedsAnalysisWorkflow(): void
    {
        $client = static::createClient();

        // Step 1: Create a company needs analysis request
        $request = $this->createCompanyRequest();
        $token = $request->getToken();

        // Step 2: Access the info page
        $client->request('GET', "/public/needs-analysis/{$token}/info");
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Analyse des besoins de formation');

        // Step 3: Access the company form
        $client->request('GET', "/public/needs-analysis/{$token}/form/company");
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="company_needs_analysis"]');

        // Step 4: Submit the company form
        $formData = $this->getCompanyFormData();
        $client->submitForm('Envoyer l\'analyse', $formData);
        
        // Should redirect to success page
        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertSelectorTextContains('h1', 'Analyse envoyée avec succès');

        // Step 5: Verify the analysis was saved
        $this->entityManager->refresh($request);
        $this->assertEquals('completed', $request->getStatus());
        $this->assertInstanceOf(CompanyNeedsAnalysis::class, $request->getCompanyAnalysis());

        // Step 6: Verify accessing the form again shows completed page
        $client->request('GET', "/public/needs-analysis/{$token}/form/company");
        $this->assertSelectorTextContains('h1', 'Analyse déjà complétée');
    }

    /**
     * Test complete workflow for individual needs analysis
     */
    public function testIndividualNeedsAnalysisWorkflow(): void
    {
        $client = static::createClient();

        // Step 1: Create an individual needs analysis request
        $request = $this->createIndividualRequest();
        $token = $request->getToken();

        // Step 2: Access the individual form
        $client->request('GET', "/public/needs-analysis/{$token}/form/individual");
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="individual_needs_analysis"]');

        // Step 3: Submit the individual form
        $formData = $this->getIndividualFormData();
        $client->submitForm('Envoyer l\'analyse', $formData);
        
        // Should redirect to success page
        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertSelectorTextContains('h1', 'Analyse envoyée avec succès');

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

        // Create an expired request
        $request = $this->createExpiredRequest();
        $token = $request->getToken();

        // Try to access the form
        $client->request('GET', "/public/needs-analysis/{$token}/form/company");
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
        $client->request('GET', "/public/needs-analysis/{$invalidToken}/form/company");
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * Test admin interface for viewing completed analysis
     */
    public function testAdminViewCompletedAnalysis(): void
    {
        $client = static::createClient();

        // Create and complete a company analysis
        $request = $this->createCompanyRequest();
        $analysis = $this->createCompanyAnalysis($request);
        $request->setCompanyAnalysis($analysis);
        $request->setStatus('completed');
        $this->entityManager->flush();

        // Access admin show page (assuming admin authentication is handled)
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

        $request = $this->createCompanyRequest();
        $token = $request->getToken();

        // Submit empty form
        $client->request('GET', "/public/needs-analysis/{$token}/form/company");
        $client->submitForm('Envoyer l\'analyse', []);

        // Should show validation errors
        $this->assertSelectorExists('.is-invalid');
        $this->assertSelectorTextContains('.invalid-feedback', 'Cette valeur ne doit pas être vide');
    }

    /**
     * Create a company needs analysis request for testing
     */
    private function createCompanyRequest(): NeedsAnalysisRequest
    {
        $request = new NeedsAnalysisRequest();
        $request->setType('company');
        $request->setRecipientEmail('test@company.com');
        $request->setRecipientName('Test Company');
        $request->setCompanyName('Test Company Ltd');
        $request->setToken($this->tokenGenerator->generateToken());
        $request->setStatus('sent');
        $request->setExpiresAt(new \DateTimeImmutable('+30 days'));
        $request->setSentAt(new \DateTimeImmutable());

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        return $request;
    }

    /**
     * Create an individual needs analysis request for testing
     */
    private function createIndividualRequest(): NeedsAnalysisRequest
    {
        $request = new NeedsAnalysisRequest();
        $request->setType('individual');
        $request->setRecipientEmail('test@individual.com');
        $request->setRecipientName('Test Individual');
        $request->setToken($this->tokenGenerator->generateToken());
        $request->setStatus('sent');
        $request->setExpiresAt(new \DateTimeImmutable('+30 days'));
        $request->setSentAt(new \DateTimeImmutable());

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        return $request;
    }

    /**
     * Create an expired request for testing
     */
    private function createExpiredRequest(): NeedsAnalysisRequest
    {
        $request = new NeedsAnalysisRequest();
        $request->setType('company');
        $request->setRecipientEmail('test@expired.com');
        $request->setRecipientName('Test Expired');
        $request->setToken($this->tokenGenerator->generateToken());
        $request->setStatus('expired');
        $request->setExpiresAt(new \DateTimeImmutable('-1 day'));
        $request->setSentAt(new \DateTimeImmutable('-31 days'));

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
            'company_needs_analysis[companyName]' => 'Test Company Ltd',
            'company_needs_analysis[responsiblePerson]' => 'John Doe',
            'company_needs_analysis[contactEmail]' => 'john@testcompany.com',
            'company_needs_analysis[contactPhone]' => '0123456789',
            'company_needs_analysis[companyAddress]' => '123 Test Street, Test City, 12345',
            'company_needs_analysis[activitySector]' => 'Technology',
            'company_needs_analysis[employeeCount]' => '75',
            'company_needs_analysis[traineesInfo][0][first_name]' => 'Alice',
            'company_needs_analysis[traineesInfo][0][last_name]' => 'Smith',
            'company_needs_analysis[traineesInfo][0][position]' => 'Developer',
            'company_needs_analysis[traineesInfo][1][first_name]' => 'Bob',
            'company_needs_analysis[traineesInfo][1][last_name]' => 'Johnson',
            'company_needs_analysis[traineesInfo][1][position]' => 'Team Lead',
            'company_needs_analysis[trainingTitle]' => 'Advanced Web Development',
            'company_needs_analysis[trainingDurationHours]' => '40',
            'company_needs_analysis[trainingLocationPreference]' => 'hybrid',
            'company_needs_analysis[trainingExpectations]' => 'Improve team technical skills and productivity',
            'company_needs_analysis[specificNeeds]' => 'Focus on modern frameworks and best practices',
        ];
    }

    /**
     * Get sample form data for individual analysis
     */
    private function getIndividualFormData(): array
    {
        return [
            'individual_needs_analysis[firstName]' => 'John',
            'individual_needs_analysis[lastName]' => 'Doe',
            'individual_needs_analysis[address]' => '123 Main Street, Test City, 12345',
            'individual_needs_analysis[phone]' => '0123456789',
            'individual_needs_analysis[email]' => 'john.doe@example.com',
            'individual_needs_analysis[status]' => 'employee',
            'individual_needs_analysis[fundingType]' => 'cpf',
            'individual_needs_analysis[desiredTrainingTitle]' => 'Advanced Web Development',
            'individual_needs_analysis[professionalObjective]' => 'Become a senior full-stack developer and lead technical projects',
            'individual_needs_analysis[currentLevel]' => 'intermediate',
            'individual_needs_analysis[desiredDurationHours]' => '40',
            'individual_needs_analysis[trainingLocationPreference]' => 'hybrid',
            'individual_needs_analysis[trainingExpectations]' => 'Learn modern frameworks and improve technical leadership skills',
            'individual_needs_analysis[specificNeeds]' => 'Focus on practical projects and real-world applications',
        ];
    }

    /**
     * Clean the database before each test
     */
    private function cleanDatabase(): void
    {
        // Remove all test data
        $this->entityManager->createQuery('DELETE FROM App\Entity\CompanyNeedsAnalysis')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\IndividualNeedsAnalysis')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\NeedsAnalysisRequest')->execute();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cleanDatabase();
    }
}