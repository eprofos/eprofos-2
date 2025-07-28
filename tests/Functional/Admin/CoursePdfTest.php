<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Entity\Training\Course;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CoursePdfTest extends WebTestCase
{
    public function testCoursePdfGeneration(): void
    {
        $client = static::createClient();

        // Get the first course
        $entityManager = $client->getContainer()->get('doctrine.orm.entity_manager');
        $course = $entityManager->getRepository(Course::class)
            ->createQueryBuilder('c')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        $this->assertNotNull($course, 'No course found in database');

        // Test PDF generation
        $client->request('GET', '/admin/course/' . $course->getId() . '/pdf');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/pdf');

        $response = $client->getResponse();
        $this->assertStringContainsString('PDF', $response->headers->get('Content-Type'));

        // Check that the filename is correct
        $contentDisposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('cours-' . $course->getSlug(), $contentDisposition);
        $this->assertStringContainsString('.pdf', $contentDisposition);
    }
}
