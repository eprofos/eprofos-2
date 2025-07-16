<?php

namespace App\Command;

use App\Entity\QuestionnaireResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:complete-questionnaire',
    description: 'Mark a questionnaire response as completed for testing',
)]
class CompleteQuestionnaireCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('token', InputArgument::REQUIRED, 'The questionnaire response token');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $token = $input->getArgument('token');

        try {
            $response = $this->entityManager->getRepository(QuestionnaireResponse::class)
                ->findOneBy(['token' => $token]);

            if (!$response) {
                $io->error('No questionnaire response found with token: ' . $token);
                return Command::FAILURE;
            }

            if ($response->isCompleted()) {
                $io->warning('Questionnaire response is already completed.');
                $io->note('You can access the completed page at: /questionnaire/completed/' . $token);
                return Command::SUCCESS;
            }

            // Mark as completed
            $response->markAsCompleted();
            
            // Set some test scores if it's an evaluation questionnaire
            if (in_array($response->getQuestionnaire()->getType(), ['evaluation', 'skills_assessment'])) {
                $response->setTotalScore(85)
                    ->setMaxPossibleScore(100)
                    ->setScorePercentage('85.00');
            }
            
            $this->entityManager->flush();

            $io->success('Questionnaire response marked as completed!');
            $io->note('You can access the completed page at: /questionnaire/completed/' . $token);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Failed to complete questionnaire: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
