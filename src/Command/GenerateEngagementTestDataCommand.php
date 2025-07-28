<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Core\AttendanceRecord;
use App\Entity\Core\StudentProgress;
use App\Entity\Training\Formation;
use App\Entity\Training\Session;
use App\Entity\User\Student;
use App\Repository\Training\FormationRepository;
use App\Repository\Training\SessionRepository;
use App\Repository\User\StudentRepository;
use App\Service\Core\DropoutPreventionService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to generate test data for Qualiopi Criterion 12 compliance testing.
 */
#[AsCommand(
    name: 'app:generate-engagement-test-data',
    description: 'Generate test data for student progress and attendance tracking',
)]
class GenerateEngagementTestDataCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StudentRepository $studentRepository,
        private FormationRepository $formationRepository,
        private SessionRepository $sessionRepository,
        private DropoutPreventionService $dropoutService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Génération de données de test pour le Critère Qualiopi 12');

        // Get existing students and formations
        $students = $this->studentRepository->findAll();
        $formations = $this->formationRepository->findAll();
        $sessions = $this->sessionRepository->findAll();

        if (empty($students)) {
            $io->error('Aucun étudiant trouvé. Veuillez d\'abord charger les fixtures.');

            return Command::FAILURE;
        }

        if (empty($formations)) {
            $io->error('Aucune formation trouvée. Veuillez d\'abord charger les fixtures.');

            return Command::FAILURE;
        }

        $io->section('Création des enregistrements de progression des étudiants');

        $progressCount = 0;
        $attendanceCount = 0;

        // Create student progress records
        foreach ($students as $student) {
            // Assign each student to 1-3 random formations
            $assignedFormations = array_rand($formations, min(3, count($formations)));

            if (!is_array($assignedFormations)) {
                $assignedFormations = [$assignedFormations];
            }

            foreach ($assignedFormations as $formationIndex) {
                $formation = $formations[$formationIndex];

                // Check if progress already exists
                $existingProgress = $this->entityManager->getRepository(StudentProgress::class)
                    ->findOneBy(['student' => $student, 'formation' => $formation])
                ;

                if (!$existingProgress) {
                    $progress = new StudentProgress();
                    $progress->setStudent($student);
                    $progress->setFormation($formation);

                    // Generate realistic data
                    $this->generateProgressData($progress);

                    $this->entityManager->persist($progress);
                    $progressCount++;
                }
            }
        }

        $this->entityManager->flush();
        $io->success("Créé {$progressCount} enregistrements de progression");

        // Create attendance records for sessions
        $io->section('Création des enregistrements d\'assiduité');

        foreach ($sessions as $session) {
            // Get students enrolled in this session's formation
            $studentsInFormation = $this->entityManager->getRepository(StudentProgress::class)
                ->findBy(['formation' => $session->getFormation()])
            ;

            foreach ($studentsInFormation as $progress) {
                $student = $progress->getStudent();

                // Check if attendance record already exists
                $existingAttendance = $this->entityManager->getRepository(AttendanceRecord::class)
                    ->findOneBy(['student' => $student, 'session' => $session])
                ;

                if (!$existingAttendance && $session->getStartDate() < new DateTime()) {
                    $attendance = new AttendanceRecord();
                    $attendance->setStudent($student);
                    $attendance->setSession($session);

                    // Generate realistic attendance data
                    $this->generateAttendanceData($attendance);

                    $this->entityManager->persist($attendance);
                    $attendanceCount++;
                }
            }
        }

        $this->entityManager->flush();
        $io->success("Créé {$attendanceCount} enregistrements d'assiduité");

        // Run risk analysis
        $io->section('Analyse des risques d\'abandon');
        $atRiskStudents = $this->dropoutService->detectAtRiskStudents();
        $io->success('Analyse terminée. ' . count($atRiskStudents) . ' étudiants identifiés à risque');

        $io->success('Génération de données de test terminée avec succès !');
        $io->note('Vous pouvez maintenant accéder au tableau de bord d\'engagement: /admin/engagement/');

        return Command::SUCCESS;
    }

    private function generateProgressData(StudentProgress $progress): void
    {
        // Simulate realistic progress data
        $daysRunning = mt_rand(1, 60);
        $startDate = new DateTime("-{$daysRunning} days");
        $progress->setStartedAt($startDate);

        // Random completion percentage (weighted towards lower for some to simulate at-risk)
        $completionWeights = [
            mt_rand(0, 20) => 30,    // Low completion (30% chance)
            mt_rand(21, 50) => 40,   // Medium-low completion (40% chance)
            mt_rand(51, 80) => 25,   // Good completion (25% chance)
            mt_rand(81, 100) => 5,    // Excellent completion (5% chance)
        ];

        $completion = array_rand($completionWeights);
        $progress->setCompletionPercentage((string) $completion);

        // Last activity (some students inactive)
        if (mt_rand(1, 100) <= 15) { // 15% chance of being inactive
            $lastActivity = new DateTime('-' . mt_rand(8, 30) . ' days');
        } else {
            $lastActivity = new DateTime('-' . mt_rand(0, 3) . ' days');
        }
        $progress->setLastActivity($lastActivity);

        // Login count and time spent
        $loginCount = max(1, (int) ($daysRunning * (mt_rand(5, 15) / 10))); // Varied login frequency
        $progress->setLoginCount($loginCount);

        $totalTimeSpent = $loginCount * mt_rand(15, 90); // 15-90 minutes per session
        $progress->setTotalTimeSpent($totalTimeSpent);

        // Attendance rate
        $attendanceRate = mt_rand(50, 100);
        if (mt_rand(1, 100) <= 20) { // 20% chance of poor attendance
            $attendanceRate = mt_rand(30, 70);
        }
        $progress->setAttendanceRate((string) $attendanceRate);

        // Missed sessions
        $missedSessions = mt_rand(0, 5);
        if ($attendanceRate < 70) {
            $missedSessions = mt_rand(2, 8); // More missed sessions for poor attendance
        }
        $progress->setMissedSessions($missedSessions);

        // Calculate engagement and detect risks
        $progress->calculateEngagementScore();
        $progress->detectRiskSignals();
    }

    private function generateAttendanceData(AttendanceRecord $attendance): void
    {
        // Weight towards present status
        $statusWeights = [
            AttendanceRecord::STATUS_PRESENT => 70,
            AttendanceRecord::STATUS_LATE => 15,
            AttendanceRecord::STATUS_PARTIAL => 10,
            AttendanceRecord::STATUS_ABSENT => 5,
        ];

        $rand = mt_rand(1, 100);
        $cumulative = 0;
        $status = AttendanceRecord::STATUS_PRESENT;

        foreach ($statusWeights as $statusOption => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                $status = $statusOption;
                break;
            }
        }

        $attendance->setStatus($status);

        // Set participation score based on status
        switch ($status) {
            case AttendanceRecord::STATUS_PRESENT:
                $participation = mt_rand(6, 10);
                break;

            case AttendanceRecord::STATUS_LATE:
                $participation = mt_rand(4, 8);
                $attendance->setMinutesLate(mt_rand(5, 30));
                break;

            case AttendanceRecord::STATUS_PARTIAL:
                $participation = mt_rand(3, 7);
                $attendance->setMinutesEarlyDeparture(mt_rand(15, 60));
                break;

            case AttendanceRecord::STATUS_ABSENT:
                $participation = 0;
                if (mt_rand(1, 100) <= 30) { // 30% chance of excused absence
                    $attendance->setExcused(true);
                    $attendance->setAbsenceReason('Maladie');
                } else {
                    $attendance->setAbsenceReason('Absence non justifiée');
                }
                break;
        }

        $attendance->setParticipationScore($participation);
        $attendance->setRecordedBy('Système de test');
    }
}
