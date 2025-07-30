<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin\Document;

use App\Entity\Document\Document;
use App\Entity\Document\DocumentMetadata;
use App\Repository\Document\DocumentMetadataRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Test for the admin_document_metadata_keys route functionality.
 */
class DocumentMetadataControllerKeysTest extends WebTestCase
{
    public function testGetAvailableKeysRoute(): void
    {
        $client = static::createClient();
        
        // Mock the repository with some test data
        $mockData = [
            ['key' => 'test_key_1', 'usage_count' => 5],
            ['key' => 'test_key_2', 'usage_count' => 3],
            ['key' => 'another_key', 'usage_count' => 1],
        ];
        
        // Make AJAX request to the route
        $client->request('GET', '/admin/document-metadata/keys', [], [], [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        
        $response = $client->getResponse();
        
        // Should return JSON response
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($response->headers->contains('Content-Type', 'application/json'));
        
        // Decode JSON response
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        
        // Each item should have 'key' and 'usage_count' fields
        foreach ($data as $item) {
            $this->assertArrayHasKey('key', $item);
            $this->assertArrayHasKey('usage_count', $item);
            $this->assertIsString($item['key']);
            $this->assertIsInt($item['usage_count']);
        }
    }
    
    public function testGetAvailableKeysWithSearch(): void
    {
        $client = static::createClient();
        
        // Make AJAX request with search parameter
        $client->request('GET', '/admin/document-metadata/keys', ['search' => 'test'], [], [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            'HTTP_ACCEPT' => 'application/json',
        ]);
        
        $response = $client->getResponse();
        
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertTrue($response->headers->contains('Content-Type', 'application/json'));
        
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
    }
    
    public function testGetAvailableKeysRequiresAjax(): void
    {
        $client = static::createClient();
        
        // Make regular (non-AJAX) request
        $client->request('GET', '/admin/document-metadata/keys');
        
        $response = $client->getResponse();
        
        // Should return 404 for non-AJAX requests
        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }
}
