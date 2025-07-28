<?php

namespace App\Command;

use App\Repository\Training\SessionRegistrationRepository;
use App\Repository\CRM\ContactRequestRepository;
use App\Repository\Analysis\NeedsAnalysisRequestRepository;
use App\Service\ProspectManagementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-prospects',
    description: 'Migrate existing SessionRegistrations and ContactRequests to create prospects'
)]
class MigrateProspectsCommand extends Command
{
    public function __construct(
        private SessionRegistrationRepository $registrationRepository,
        private ContactRequestRepository $contactRequestRepository,
        private NeedsAnalysisRequestRepository $needsAnalysisRepository,
        private ProspectManagementService $prospectService,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Execute the migration without persisting changes')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Process records in batches', 100)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = $input->getOption('dry-run');
        $batchSize = (int) $input->getOption('batch-size');

        $io->title('Migrating existing data to create prospects');

        if ($isDryRun) {
            $io->note('DRY RUN MODE - No changes will be persisted');
        }

        // Migrate session registrations
        $this->migrateSessionRegistrations($io, $isDryRun, $batchSize);
        
        // Migrate contact requests
        $this->migrateContactRequests($io, $isDryRun, $batchSize);
        
        // Migrate needs analysis requests
        $this->migrateNeedsAnalysisRequests($io, $isDryRun, $batchSize);

        // Merge duplicate prospects
        if (!$isDryRun) {
            $io->section('Merging duplicate prospects');
            $mergedCount = $this->prospectService->mergeDuplicateProspects();
            $io->success("Merged {$mergedCount} duplicate prospects");
        }

        $io->success('Migration completed successfully!');
        return Command::SUCCESS;
    }

    private function migrateSessionRegistrations(SymfonyStyle $io, bool $isDryRun, int $batchSize): void
    {
        $io->section('Migrating Session Registrations');
        
        // Get registrations without prospects
        $registrations = $this->registrationRepository->createQueryBuilder('sr')
            ->where('sr.prospect IS NULL')
            ->getQuery()
            ->getResult();

        $total = count($registrations);
        $io->note("Found {$total} session registrations to migrate");

        if ($total === 0) {
            return;
        }

        $progressBar = $io->createProgressBar($total);
        $processed = 0;

        foreach ($registrations as $registration) {
            try {
                if (!$isDryRun) {
                    $this->prospectService->createProspectFromSessionRegistration($registration);
                }
                
                $processed++;
                
                // Flush in batches
                if (!$isDryRun && $processed % $batchSize === 0) {
                    $this->entityManager->flush();
                    $this->entityManager->clear();
                }
                
            } catch (\Exception $e) {
                $io->error("Failed to migrate registration {$registration->getId()}: " . $e->getMessage());
            }
            
            $progressBar->advance();
        }

        if (!$isDryRun) {
            $this->entityManager->flush();
        }

        $progressBar->finish();
        $io->newLine();
        $io->success("Migrated {$processed} session registrations");
    }

    private function migrateContactRequests(SymfonyStyle $io, bool $isDryRun, int $batchSize): void
    {
        $io->section('Migrating Contact Requests');
        
        // Get contact requests without prospects
        $contactRequests = $this->contactRequestRepository->createQueryBuilder('cr')
            ->where('cr.prospect IS NULL')
            ->getQuery()
            ->getResult();

        $total = count($contactRequests);
        $io->note("Found {$total} contact requests to migrate");

        if ($total === 0) {
            return;
        }

        $progressBar = $io->createProgressBar($total);
        $processed = 0;

        foreach ($contactRequests as $contactRequest) {
            try {
                if (!$isDryRun) {
                    $this->prospectService->createProspectFromContactRequest($contactRequest);
                }
                
                $processed++;
                
                // Flush in batches
                if (!$isDryRun && $processed % $batchSize === 0) {
                    $this->entityManager->flush();
                    $this->entityManager->clear();
                }
                
            } catch (\Exception $e) {
                $io->error("Failed to migrate contact request {$contactRequest->getId()}: " . $e->getMessage());
            }
            
            $progressBar->advance();
        }

        if (!$isDryRun) {
            $this->entityManager->flush();
        }

        $progressBar->finish();
        $io->newLine();
        $io->success("Migrated {$processed} contact requests");
    }

    private function migrateNeedsAnalysisRequests(SymfonyStyle $io, bool $isDryRun, int $batchSize): void
    {
        $io->section('Migrating Needs Analysis Requests');
        
        // Get needs analysis requests without prospects
        $needsAnalysisRequests = $this->needsAnalysisRepository->createQueryBuilder('nar')
            ->where('nar.prospect IS NULL')
            ->getQuery()
            ->getResult();

        $total = count($needsAnalysisRequests);
        $io->note("Found {$total} needs analysis requests to migrate");

        if ($total === 0) {
            return;
        }

        $progressBar = $io->createProgressBar($total);
        $processed = 0;

        foreach ($needsAnalysisRequests as $needsAnalysis) {
            try {
                if (!$isDryRun) {
                    $this->prospectService->createProspectFromNeedsAnalysis($needsAnalysis);
                }
                
                $processed++;
                
                // Flush in batches
                if (!$isDryRun && $processed % $batchSize === 0) {
                    $this->entityManager->flush();
                    $this->entityManager->clear();
                }
                
            } catch (\Exception $e) {
                $io->error("Failed to migrate needs analysis {$needsAnalysis->getId()}: " . $e->getMessage());
            }
            
            $progressBar->advance();
        }

        if (!$isDryRun) {
            $this->entityManager->flush();
        }

        $progressBar->finish();
        $io->newLine();
        $io->success("Migrated {$processed} needs analysis requests");
    }
}
