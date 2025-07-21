<?php

namespace App\DataFixtures;

use App\Entity\User\Student;
use App\Entity\AttendanceRecord;
use App\Entity\Training\Session;
use App\Entity\StudentProgress;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

/**
 * AttendanceRecordFixtures - Generate realistic attendance data for Qualiopi Criterion 12 testing
 * 
 * Creates comprehensive attendance records with varied participation levels,
 * absence patterns, and engagement metrics to test the attendance monitoring system.
 */
class AttendanceRecordFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        
        // Get all sessions and students from existing fixtures
        $sessions = $manager->getRepository(Session::class)->findAll();
        $students = $manager->getRepository(Student::class)->findAll();
        $progressRecords = $manager->getRepository(StudentProgress::class)->findAll();
        
        if (empty($sessions) || empty($students)) {
            echo "⚠️  Warning: No sessions or students found. Make sure to load SessionFixtures and StudentFixtures first.\n";
            return;
        }
        
        $attendanceCount = 0;
        $absenteeCount = 0;
        $lateCount = 0;
        
        // Create attendance records for past sessions
        foreach ($sessions as $session) {
            // Only create attendance for past sessions
            if ($session->getStartDate() > new \DateTime()) {
                continue;
            }
            
            // Find students enrolled in this session's formation
            $enrolledStudents = $this->getStudentsForSession($session, $progressRecords, $students);
            
            foreach ($enrolledStudents as $student) {
                $attendance = $this->createAttendanceRecord($student, $session, $faker);
                $manager->persist($attendance);
                
                $attendanceCount++;
                
                if ($attendance->isAbsent()) {
                    $absenteeCount++;
                } elseif ($attendance->isLate()) {
                    $lateCount++;
                }
            }
        }
        
        $manager->flush();
        
        echo "✅ Attendance Records: Created {$attendanceCount} attendance records\n";
        echo "⚠️  Absences: {$absenteeCount} recorded absences\n";
        echo "🕐 Late Arrivals: {$lateCount} recorded late arrivals\n";
    }
    
    private function getStudentsForSession(Session $session, array $progressRecords, array $students): array
    {
        // Find students who have progress records for this session's formation
        $enrolledStudents = [];
        
        foreach ($progressRecords as $progress) {
            if ($progress->getFormation() === $session->getFormation()) {
                $enrolledStudents[] = $progress->getStudent();
            }
        }
        
        // If no enrolled students found, randomly assign some students to the session
        if (empty($enrolledStudents)) {
            $randomCount = min(rand(5, 15), count($students));
            $enrolledStudents = array_slice($students, 0, $randomCount);
        }
        
        return array_unique($enrolledStudents);
    }
    
    private function createAttendanceRecord(Student $student, Session $session, $faker): AttendanceRecord
    {
        $attendance = new AttendanceRecord();
        $attendance->setStudent($student);
        $attendance->setSession($session);
        
        // Get student's engagement profile to influence attendance
        $studentProgress = $this->getStudentProgress($student, $session);
        $attendanceProfile = $this->determineAttendanceProfile($studentProgress, $faker);
        
        // Set attendance status based on profile
        $status = $this->generateAttendanceStatus($attendanceProfile, $faker);
        $attendance->setStatus($status);
        
        // Set specific data based on status
        $this->setStatusSpecificData($attendance, $status, $session, $faker);
        
        // Set participation score
        $participationScore = $this->generateParticipationScore($status, $attendanceProfile, $faker);
        $attendance->setParticipationScore($participationScore);
        
        // Set recording information
        $attendance->setRecordedBy($faker->randomElement([
            'Formateur Principal',
            'Assistant Pédagogique',
            'Coordinateur Formation',
            'Système Automatique'
        ]));
        
        // Set recorded time (usually same day or next day)
        $recordedAt = new \DateTime($session->getStartDate()->format('Y-m-d H:i:s'));
        $hours = $faker->numberBetween(1, 48);
        $recordedAt->modify("+{$hours} hours");
        $attendance->setRecordedAt($recordedAt);
        
        return $attendance;
    }
    
    private function getStudentProgress(Student $student, Session $session): ?StudentProgress
    {
        // This would normally query the database, but for fixtures we'll simulate
        return null; // We'll use defaults if no progress found
    }
    
    private function determineAttendanceProfile(mixed $studentProgress, $faker): string
    {
        // If we have progress data, use it to influence attendance
        if ($studentProgress && $studentProgress->isAtRiskOfDropout()) {
            return 'poor'; // At-risk students have poor attendance
        }
        
        // Otherwise, use weighted random distribution
        $profiles = [
            'excellent' => 60,  // 60% chance - Good attenders
            'good' => 25,       // 25% chance - Mostly good
            'average' => 10,    // 10% chance - Some issues
            'poor' => 5         // 5% chance - Frequent problems
        ];
        
        return $faker->randomElement(array_merge(...array_map(
            fn($profile, $weight) => array_fill(0, $weight, $profile),
            array_keys($profiles),
            array_values($profiles)
        )));
    }
    
    private function generateAttendanceStatus(string $profile, $faker): string
    {
        $statusDistribution = match ($profile) {
            'excellent' => [
                AttendanceRecord::STATUS_PRESENT => 90,
                AttendanceRecord::STATUS_LATE => 8,
                AttendanceRecord::STATUS_PARTIAL => 2,
                AttendanceRecord::STATUS_ABSENT => 0
            ],
            'good' => [
                AttendanceRecord::STATUS_PRESENT => 75,
                AttendanceRecord::STATUS_LATE => 15,
                AttendanceRecord::STATUS_PARTIAL => 7,
                AttendanceRecord::STATUS_ABSENT => 3
            ],
            'average' => [
                AttendanceRecord::STATUS_PRESENT => 60,
                AttendanceRecord::STATUS_LATE => 20,
                AttendanceRecord::STATUS_PARTIAL => 12,
                AttendanceRecord::STATUS_ABSENT => 8
            ],
            'poor' => [
                AttendanceRecord::STATUS_PRESENT => 40,
                AttendanceRecord::STATUS_LATE => 20,
                AttendanceRecord::STATUS_PARTIAL => 15,
                AttendanceRecord::STATUS_ABSENT => 25
            ]
        };
        
        $rand = $faker->numberBetween(1, 100);
        $cumulative = 0;
        
        foreach ($statusDistribution as $status => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $status;
            }
        }
        
        return AttendanceRecord::STATUS_PRESENT; // Fallback
    }
    
    private function setStatusSpecificData(AttendanceRecord $attendance, string $status, Session $session, $faker): void
    {
        switch ($status) {
            case AttendanceRecord::STATUS_LATE:
                // Set arrival time and calculate lateness
                $sessionStart = $session->getStartDate();
                $minutesLate = $faker->numberBetween(5, 45);
                $arrivalTime = new \DateTime($sessionStart->format('Y-m-d H:i:s'));
                $arrivalTime->modify("+{$minutesLate} minutes");
                
                $attendance->setArrivalTime($arrivalTime);
                $attendance->setMinutesLate($minutesLate);
                break;
                
            case AttendanceRecord::STATUS_PARTIAL:
                // Set departure time and calculate early departure
                $sessionEnd = $session->getEndDate();
                $minutesEarly = $faker->numberBetween(15, 120);
                $departureTime = new \DateTime($sessionEnd->format('Y-m-d H:i:s'));
                $departureTime->modify("-{$minutesEarly} minutes");
                
                $attendance->setDepartureTime($departureTime);
                $attendance->setMinutesEarlyDeparture($minutesEarly);
                
                // Might also be late
                if ($faker->boolean(30)) {
                    $minutesLate = $faker->numberBetween(5, 20);
                    $arrivalTime = new \DateTime($session->getStartDate()->format('Y-m-d H:i:s'));
                    $arrivalTime->modify("+{$minutesLate} minutes");
                    $attendance->setArrivalTime($arrivalTime);
                    $attendance->setMinutesLate($minutesLate);
                }
                break;
                
            case AttendanceRecord::STATUS_ABSENT:
                // Set absence details
                $excused = $faker->boolean(40); // 40% of absences are excused
                $attendance->setExcused($excused);
                
                if ($excused) {
                    $attendance->setAbsenceReason($faker->randomElement([
                        'Maladie avec certificat médical',
                        'Urgence familiale',
                        'Problème de transport',
                        'Congé autorisé',
                        'Rendez-vous médical',
                        'Formation professionnelle conflictuelle'
                    ]));
                } else {
                    $attendance->setAbsenceReason($faker->randomElement([
                        'Absence non justifiée',
                        'Oubli de prévenir',
                        'Surcharge de travail',
                        'Motivation insuffisante',
                        'Problème personnel non communiqué',
                        null // Sometimes no reason given
                    ]));
                }
                break;
        }
        
        // Add admin notes for some records
        if ($faker->boolean(20)) {
            $attendance->setAdminNotes($faker->randomElement([
                'Étudiant très participatif',
                'A posé de bonnes questions',
                'Semble fatigué, à surveiller',
                'Difficultés de compréhension observées',
                'Excellent engagement',
                'Besoin de soutien supplémentaire',
                'Progrès remarquables',
                'À encourager davantage'
            ]));
        }
    }
    
    private function generateParticipationScore(string $status, string $profile, $faker): int
    {
        if ($status === AttendanceRecord::STATUS_ABSENT) {
            return 0;
        }
        
        $baseScore = match ($profile) {
            'excellent' => $faker->numberBetween(8, 10),
            'good' => $faker->numberBetween(6, 9),
            'average' => $faker->numberBetween(4, 7),
            'poor' => $faker->numberBetween(2, 5)
        };
        
        // Adjust score based on attendance status
        $adjustment = match ($status) {
            AttendanceRecord::STATUS_PRESENT => 0,
            AttendanceRecord::STATUS_LATE => $faker->numberBetween(-2, -1),
            AttendanceRecord::STATUS_PARTIAL => $faker->numberBetween(-3, -1),
            default => 0
        };
        
        return max(0, min(10, $baseScore + $adjustment));
    }
    
    public function getDependencies(): array
    {
        return [
            StudentFixtures::class,
            SessionFixtures::class,
            StudentProgressFixtures::class,
        ];
    }
}
