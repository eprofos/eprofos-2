<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\CRM\ContactRequest;
use App\Service\CRM\ProspectManagementService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-prospect-integration',
    description: 'Test the prospect integration by creating a test contact request and prospect',
)]
class TestProspectIntegrationCommand extends Command
{
    public function __construct(
        private ProspectManagementService $prospectService,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Testing Prospect Integration');

        // Get current prospect count
        $currentCount = $this->entityManager->createQuery('SELECT COUNT(p.id) FROM App\Entity\Prospect p')
            ->getSingleScalarResult()
        ;

        $io->note("Current prospect count: {$currentCount}");

        // Create a test contact request
        $contactRequest = new ContactRequest();
        $contactRequest
            ->setType('quote')
            ->setFirstName('Test')
            ->setLastName('Integration')
            ->setEmail('test.integration@example.com')
            ->setPhone('0123456789')
            ->setCompany('Test Integration Company')
            ->setMessage('This is a test message to verify prospect integration works correctly.')
        ;

        $this->entityManager->persist($contactRequest);
        $this->entityManager->flush();

        $io->success("Contact request created with ID: {$contactRequest->getId()}");

        // Create prospect from contact request
        try {
            $prospect = $this->prospectService->createProspectFromContactRequest($contactRequest);

            $io->success('Prospect created successfully!');
            $io->table(
                ['Property', 'Value'],
                [
                    ['ID', $prospect->getId()],
                    ['Email', $prospect->getEmail()],
                    ['Full Name', $prospect->getFullName()],
                    ['Status', $prospect->getStatus()],
                    ['Source', $prospect->getSource()],
                    ['Lead Score', $prospect->getLeadScore()],
                    ['Company', $prospect->getCompany()],
                ],
            );

            // Get new prospect count
            $newCount = $this->entityManager->createQuery('SELECT COUNT(p.id) FROM App\Entity\Prospect p')
                ->getSingleScalarResult()
            ;

            $io->note("New prospect count: {$newCount} (increased by " . ($newCount - $currentCount) . ')');

            // Check if contact request is linked to prospect
            $this->entityManager->refresh($contactRequest);
            if ($contactRequest->getProspect()) {
                $io->success("Contact request is properly linked to prospect ID: {$contactRequest->getProspect()->getId()}");
            } else {
                $io->error('Contact request is NOT linked to prospect!');
            }

            // Test the getAllInteractions method
            $interactions = $prospect->getAllInteractions();
            $io->note('Found ' . count($interactions) . ' interactions for this prospect');

            if (!empty($interactions)) {
                foreach ($interactions as $interaction) {
                    $io->text("- {$interaction['type']}: {$interaction['title']} ({$interaction['date']->format('Y-m-d H:i')})");
                }
            }
        } catch (Exception $e) {
            $io->error('Failed to create prospect: ' . $e->getMessage());

            return Command::FAILURE;
        }

        $io->success('Prospect integration test completed successfully!');

        return Command::SUCCESS;
    }
}
