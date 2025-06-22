<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\NeedsAnalysisRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to automatically expire needs analysis requests that have passed their expiration date.
 * 
 * This command should be run regularly (e.g., daily via cron) to maintain data consistency
 * and ensure that expired requests are properly marked as expired.
 */
#[AsCommand(
    name: 'app:needs-analysis:expire',
    description: 'Mark expired needs analysis requests as expired'
)]
class NeedsAnalysisExpireCommand extends Command
{
    public function __construct(
        private readonly NeedsAnalysisRequestRepository $requestRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    /**
     * Configure the command options and arguments.
     */
    protected function configure(): void
    {
        $this
            ->addOption(
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                'Show what would be expired without actually updating the database'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force expiration even if requests are not yet expired (for testing)'
            )
            ->setHelp(
                'This command finds all needs analysis requests that have passed their expiration date ' .
                'and marks them as expired. Use --dry-run to see what would be expired without making changes.'
            );
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDryRun = $input->getOption('dry-run');
        $isForce = $input->getOption('force');

        $io->title('Needs Analysis Expiration Command');

        try {
            // Get expired requests
            $expiredRequests = $this->requestRepository->findExpiredRequests();
            
            if (empty($expiredRequests)) {
                $io->success('No expired requests found.');
                return Command::SUCCESS;
            }

            $io->section(sprintf('Found %d expired request(s)', count($expiredRequests)));

            // Display expired requests
            $tableData = [];
            foreach ($expiredRequests as $request) {
                $tableData[] = [
                    $request->getId(),
                    $request->getContactEmail(),
                    $request->getRequestType(),
                    $request->getStatus(),
                    $request->getCreatedAt()->format('Y-m-d H:i:s'),
                    $request->getExpiresAt()->format('Y-m-d H:i:s'),
                ];
            }

            $io->table(
                ['ID', 'Email', 'Type', 'Status', 'Created At', 'Expires At'],
                $tableData
            );

            if ($isDryRun) {
                $io->note('DRY RUN: No changes will be made to the database.');
                return Command::SUCCESS;
            }

            // Confirm before proceeding (unless force is used)
            if (!$isForce && !$io->confirm('Do you want to mark these requests as expired?', false)) {
                $io->info('Operation cancelled.');
                return Command::SUCCESS;
            }

            // Mark requests as expired
            $expiredCount = $this->requestRepository->markExpiredRequests();

            if ($expiredCount > 0) {
                $this->entityManager->flush();
                
                $io->success(sprintf('Successfully marked %d request(s) as expired.', $expiredCount));
                
                // Log the operation
                $this->logger->info('Needs analysis requests expired', [
                    'expired_count' => $expiredCount,
                    'command' => 'app:needs-analysis:expire'
                ]);
            } else {
                $io->info('No requests were expired.');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error(sprintf('An error occurred: %s', $e->getMessage()));
            
            $this->logger->error('Error in needs analysis expiration command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Command::FAILURE;
        }
    }
}