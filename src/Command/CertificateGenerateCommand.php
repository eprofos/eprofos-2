<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Core\StudentEnrollment;
use App\Service\Student\CertificateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to generate certificates for completed enrollments.
 */
#[AsCommand(
    name: 'app:certificate:generate',
    description: 'Generate certificate for a completed enrollment'
)]
class CertificateGenerateCommand extends Command
{
    public function __construct(
        private readonly CertificateService $certificateService,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('enrollment-id', InputArgument::REQUIRED, 'The enrollment ID')
            ->setHelp('This command generates a certificate for a completed enrollment.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $enrollmentId = (int) $input->getArgument('enrollment-id');

        $enrollment = $this->entityManager->getRepository(StudentEnrollment::class)->find($enrollmentId);

        if (!$enrollment) {
            $io->error(sprintf('Enrollment with ID %d not found.', $enrollmentId));
            return Command::FAILURE;
        }

        $io->section('Enrollment Information');
        $io->table(
            ['Field', 'Value'],
            [
                ['ID', $enrollment->getId()],
                ['Student', $enrollment->getStudent()?->getFullName()],
                ['Formation', $enrollment->getSessionRegistration()?->getSession()?->getFormation()?->getTitle()],
                ['Status', $enrollment->getStatus()],
                ['Completed At', $enrollment->getCompletedAt()?->format('Y-m-d H:i:s') ?? 'Not completed'],
            ]
        );

        // Check eligibility
        $io->section('Checking Certificate Eligibility');
        
        if (!$this->certificateService->checkCompletionEligibility($enrollment)) {
            $io->error('This enrollment is not eligible for certificate generation.');
            $io->note('Reasons could include:');
            $io->listing([
                'Enrollment is not marked as completed',
                'Progress completion is less than 100%',
                'Not all modules are completed',
                'Minimum scores not met for QCMs',
                'Attendance requirements not met',
            ]);
            return Command::FAILURE;
        }

        $io->success('Enrollment is eligible for certificate generation!');

        if (!$io->confirm('Do you want to generate the certificate?', false)) {
            $io->note('Certificate generation cancelled.');
            return Command::SUCCESS;
        }

        try {
            $io->section('Generating Certificate');
            $certificate = $this->certificateService->generateCertificate($enrollment);

            $io->success('Certificate generated successfully!');
            
            $io->table(
                ['Field', 'Value'],
                [
                    ['Certificate Number', $certificate->getCertificateNumber()],
                    ['Verification Code', $certificate->getVerificationCode()],
                    ['Issue Date', $certificate->getIssuedAt()->format('Y-m-d H:i:s')],
                    ['Final Score', $certificate->getFinalScore() ? $certificate->getFinalScore() . '%' : 'N/A'],
                    ['Grade', $certificate->getGradeLabel() ?? 'N/A'],
                    ['Status', $certificate->getStatusLabel()],
                    ['PDF Path', $certificate->getPdfPath() ?? 'Not generated'],
                ]
            );

            $io->note([
                'The certificate has been generated and saved to the database.',
                'PDF file: ' . ($certificate->getPdfPath() ? 'Generated' : 'Failed to generate'),
                'Email delivery: Attempted (check logs for details)',
            ]);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to generate certificate: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
