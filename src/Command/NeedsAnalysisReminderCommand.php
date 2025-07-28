<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\Analysis\NeedsAnalysisRequestRepository;
use App\Service\AnalysisEmailNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to send reminder emails for pending needs analysis requests.
 * 
 * This command identifies requests that have been sent but not completed
 * and sends reminder emails to encourage completion before expiration.
 */
#[AsCommand(
    name: 'app:needs-analysis:remind',
    description: 'Send reminder emails for pending needs analysis requests'
)]
class NeedsAnalysisReminderCommand extends Command
{
    public function __construct(
        private readonly NeedsAnalysisRequestRepository $requestRepository,
        private readonly AnalysisEmailNotificationService $emailService,
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
                'days-before-expiry',
                null,
                InputOption::VALUE_REQUIRED,
                'Send reminders for requests expiring within this many days',
                7
            )
            ->addOption(
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                'Show what reminders would be sent without actually sending emails'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Send reminders even if they were recently sent'
            )
            ->setHelp(
                'This command sends reminder emails to users who have pending needs analysis requests ' .
                'that are approaching their expiration date. By default, reminders are sent for requests ' .
                'expiring within 7 days.'
            );
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $daysBeforeExpiry = (int) $input->getOption('days-before-expiry');
        $isDryRun = $input->getOption('dry-run');
        $isForce = $input->getOption('force');

        $io->title('Needs Analysis Reminder Command');

        try {
            // Calculate the date threshold for reminders
            $reminderDate = new \DateTime();
            $reminderDate->modify(sprintf('+%d days', $daysBeforeExpiry));

            $io->info(sprintf(
                'Looking for pending requests expiring before %s (%d days from now)',
                $reminderDate->format('Y-m-d'),
                $daysBeforeExpiry
            ));

            // Get requests that need reminders
            $pendingRequests = $this->requestRepository->findRequestsExpiringSoon($daysBeforeExpiry);

            if (empty($pendingRequests)) {
                $io->success('No pending requests found that need reminders.');
                return Command::SUCCESS;
            }

            // Filter out requests that already received recent reminders (unless forced)
            $requestsToRemind = [];
            foreach ($pendingRequests as $request) {
                if ($isForce || $this->shouldSendReminder($request)) {
                    $requestsToRemind[] = $request;
                }
            }

            if (empty($requestsToRemind)) {
                $io->success('No requests need reminders at this time (recent reminders already sent).');
                return Command::SUCCESS;
            }

            $io->section(sprintf('Found %d request(s) that need reminders', count($requestsToRemind)));

            // Display requests that will receive reminders
            $tableData = [];
            foreach ($requestsToRemind as $request) {
                $daysUntilExpiry = $request->getExpiresAt()->diff(new \DateTime())->days;
                $tableData[] = [
                    $request->getId(),
                    $request->getContactEmail(),
                    $request->getRequestType(),
                    $request->getCreatedAt()->format('Y-m-d'),
                    $request->getExpiresAt()->format('Y-m-d'),
                    $daysUntilExpiry,
                    $request->getLastReminderSentAt() ? $request->getLastReminderSentAt()->format('Y-m-d') : 'Never'
                ];
            }

            $io->table(
                ['ID', 'Email', 'Type', 'Created', 'Expires', 'Days Left', 'Last Reminder'],
                $tableData
            );

            if ($isDryRun) {
                $io->note('DRY RUN: No emails will be sent.');
                return Command::SUCCESS;
            }

            // Confirm before proceeding (unless force is used)
            if (!$isForce && !$io->confirm('Do you want to send reminder emails?', false)) {
                $io->info('Operation cancelled.');
                return Command::SUCCESS;
            }

            // Send reminder emails
            $successCount = 0;
            $errorCount = 0;

            $progressBar = $io->createProgressBar(count($requestsToRemind));
            $progressBar->start();

            foreach ($requestsToRemind as $request) {
                try {
                    $this->emailService->sendExpirationReminder($request);
                    
                    // Update the last reminder sent timestamp
                    $request->setLastReminderSentAt(new \DateTime());
                    $successCount++;
                    
                } catch (\Exception $e) {
                    $errorCount++;
                    $this->logger->error('Failed to send reminder email', [
                        'request_id' => $request->getId(),
                        'email' => $request->getContactEmail(),
                        'error' => $e->getMessage()
                    ]);
                }
                
                $progressBar->advance();
            }

            $progressBar->finish();
            $io->newLine(2);

            // Persist changes
            if ($successCount > 0) {
                $this->entityManager->flush();
            }

            // Display results
            if ($successCount > 0) {
                $io->success(sprintf('Successfully sent %d reminder email(s).', $successCount));
            }

            if ($errorCount > 0) {
                $io->warning(sprintf('%d email(s) failed to send. Check logs for details.', $errorCount));
            }

            // Log the operation
            $this->logger->info('Needs analysis reminders sent', [
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'days_before_expiry' => $daysBeforeExpiry,
                'command' => 'app:needs-analysis:remind'
            ]);

            return $errorCount > 0 ? Command::FAILURE : Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error(sprintf('An error occurred: %s', $e->getMessage()));
            
            $this->logger->error('Error in needs analysis reminder command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Command::FAILURE;
        }
    }

    /**
     * Determine if a reminder should be sent for the given request.
     * 
     * Reminders are not sent if one was already sent within the last 3 days
     * to avoid spamming users.
     */
    private function shouldSendReminder($request): bool
    {
        $lastReminderSent = $request->getLastReminderSentAt();
        
        if (!$lastReminderSent) {
            return true; // No reminder sent yet
        }

        // Don't send reminder if one was sent within the last 3 days
        $threeDaysAgo = new \DateTime('-3 days');
        return $lastReminderSent < $threeDaysAgo;
    }
}