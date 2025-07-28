<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User\Admin;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:test-password',
    description: 'Test password verification for admin user',
)]
class TestPasswordCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userRepository = $this->entityManager->getRepository(Admin::class);
        $adminUser = $userRepository->findOneBy(['email' => 'admin@eprofos.fr']);

        if (!$adminUser) {
            $output->writeln('<error>Admin user not found!</error>');

            return Command::FAILURE;
        }

        $output->writeln('User found:');
        $output->writeln('Email: ' . $adminUser->getEmail());
        $output->writeln('Roles: ' . json_encode($adminUser->getRoles()));
        $output->writeln('Is Active: ' . ($adminUser->isActive() ? 'true' : 'false'));
        $output->writeln('User Identifier: ' . $adminUser->getUserIdentifier());

        // Test password verification
        $isPasswordValid = $this->passwordHasher->isPasswordValid($adminUser, 'admin123');
        $output->writeln('Password "admin123" is valid: ' . ($isPasswordValid ? 'YES' : 'NO'));

        // Try different passwords
        $isPasswordValid2 = $this->passwordHasher->isPasswordValid($adminUser, 'Admin123');
        $output->writeln('Password "Admin123" is valid: ' . ($isPasswordValid2 ? 'YES' : 'NO'));

        $isPasswordValid3 = $this->passwordHasher->isPasswordValid($adminUser, '');
        $output->writeln('Empty password is valid: ' . ($isPasswordValid3 ? 'YES' : 'NO'));

        return Command::SUCCESS;
    }
}
