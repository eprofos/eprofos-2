<?php

namespace App\Command;

use App\Entity\User\Student;
use App\Entity\Training\Formation;
use App\Service\Security\ContentAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-access-control',
    description: 'Test the content access control system',
)]
class TestAccessControlCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ContentAccessService $contentAccessService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Testing Content Access Control System');

        // Get enrolled student and their formation for testing
                // Test 1: Check access for an enrolled student (should have access)
        $io->section('Test 1: Enrolled Student Access');
        $enrolledStudent = $this->entityManager->getRepository(Student::class)->find(32);
        $formation = $this->entityManager->getRepository(Formation::class)->find(18);

        if (!$enrolledStudent || !$formation) {
            $io->error('Could not find test student or formation');
            return Command::FAILURE;
        }

        $io->text(sprintf('Student: %s (ID: %d)', $enrolledStudent->getFullName(), $enrolledStudent->getId()));
        $io->text(sprintf('Formation: %s (ID: %d)', $formation->getTitle(), $formation->getId()));

        $canAccess = $this->contentAccessService->canAccessFormation($enrolledStudent, $formation);
        $io->text(sprintf('Can access formation: %s', $canAccess ? 'YES' : 'NO'));

        if ($canAccess) {
            $enrollments = $this->contentAccessService->getStudentEnrollments($enrolledStudent);
            if (!empty($enrollments)) {
                $enrollment = $enrollments[0];
                $io->text('Enrollment found:');
                $io->text(sprintf('  - Session: %s', $enrollment->getSession()?->getName() ?? 'Unknown'));
                $io->text(sprintf('  - Status: %s', $enrollment->getStatus()));
                $io->text(sprintf('  - Enrollment Date: %s', $enrollment->getEnrolledAt()?->format('Y-m-d H:i:s')));
            }
        }

        // Test 2: Check access for a non-enrolled student (should NOT have access)
        $io->section('Test 2: Non-Enrolled Student Access');
        $nonEnrolledStudent = $this->entityManager->getRepository(Student::class)->createQueryBuilder('s')
            ->leftJoin('App\Entity\Core\StudentEnrollment', 'se', 'WITH', 'se.student = s.id')
            ->leftJoin('se.sessionRegistration', 'sr')
            ->leftJoin('sr.session', 'session')
            ->where('session.formation != :formation OR se.id IS NULL')
            ->setParameter('formation', $formation)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$nonEnrolledStudent) {
            // Create a test case with different formation
            $differentFormation = $this->entityManager->getRepository(Formation::class)
                ->createQueryBuilder('f')
                ->where('f.id != :formation_id')
                ->setParameter('formation_id', $formation->getId())
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($differentFormation) {
                $io->text(sprintf('Testing access to different formation: %s', $differentFormation->getTitle()));
                $canAccessDifferent = $this->contentAccessService->canAccessFormation($enrolledStudent, $differentFormation);
                $io->text(sprintf('Can access different formation: %s', $canAccessDifferent ? 'YES' : 'NO'));
            }
        } else {
            $io->text(sprintf('Non-enrolled student: %s (ID: %d)', $nonEnrolledStudent->getFullName(), $nonEnrolledStudent->getId()));
            $canAccessNonEnrolled = $this->contentAccessService->canAccessFormation($nonEnrolledStudent, $formation);
            $io->text(sprintf('Can access formation: %s', $canAccessNonEnrolled ? 'YES' : 'NO'));
        }

        // Test content hierarchy access
        $io->section('Test 3: Content Hierarchy Access');
        $modules = $formation->getModules();
        if (!$modules->isEmpty()) {
            $module = $modules->first();
            $io->text(sprintf('Module: %s', $module->getTitle()));
            $canAccessModule = $this->contentAccessService->canAccessModule($enrolledStudent, $module);
            $io->text(sprintf('Can access module: %s', $canAccessModule ? 'YES' : 'NO'));

            $chapters = $module->getChapters();
            if (!$chapters->isEmpty()) {
                $chapter = $chapters->first();
                $io->text(sprintf('Chapter: %s', $chapter->getTitle()));
                $canAccessChapter = $this->contentAccessService->canAccessChapter($enrolledStudent, $chapter);
                $io->text(sprintf('Can access chapter: %s', $canAccessChapter ? 'YES' : 'NO'));
            }
        }

        $io->success('Content access control system test completed successfully!');

        return Command::SUCCESS;
    }
}
