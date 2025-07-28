<?php

namespace App\Entity\Core;

use App\Entity\Training\Formation;
use App\Entity\Training\Module;
use App\Entity\Training\Chapter;
use App\Entity\User\Student;
use App\Entity\Alternance\AlternanceContract;
use App\Repository\Core\StudentProgressRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * StudentProgress entity for tracking student advancement and engagement in training programs
 * 
 * This entity is critical for Qualiopi Criterion 12 compliance, providing detailed tracking
 * of student progress, engagement metrics, and early dropout risk detection.
 */
#[ORM\Entity(repositoryClass: StudentProgressRepository::class)]
#[ORM\Table(name: 'student_progress')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['student_id', 'formation_id'], name: 'idx_student_formation')]
#[ORM\Index(columns: ['at_risk_of_dropout'], name: 'idx_at_risk')]
#[ORM\Index(columns: ['last_activity'], name: 'idx_last_activity')]
class StudentProgress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Available alternance status options
     */
    public const ALTERNANCE_STATUS_OPTIONS = [
        'active' => 'Actif',
        'paused' => 'En pause',
        'completed' => 'Terminé',
        'terminated' => 'Interrompu',
        'at_risk' => 'À risque',
        'needs_support' => 'Nécessite un accompagnement'
    ];

    #[ORM\ManyToOne(targetEntity: Student::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Student $student = null;

    #[ORM\ManyToOne(targetEntity: Formation::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Formation $formation = null;

    #[ORM\ManyToOne(targetEntity: Module::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Module $currentModule = null;

    #[ORM\ManyToOne(targetEntity: Chapter::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Chapter $currentChapter = null;

    /**
     * Overall completion percentage (0-100)
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    #[Assert\Range(
        min: 0,
        max: 100,
        notInRangeMessage: 'Le pourcentage de completion doit être entre {{ min }}% et {{ max }}%'
    )]
    private ?string $completionPercentage = '0.00';

    /**
     * Module-specific completion tracking
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $moduleProgress = null;

    /**
     * Chapter-specific completion tracking
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $chapterProgress = null;

    /**
     * Last activity timestamp
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $lastActivity = null;

    /**
     * Engagement score (0-100) based on various factors
     */
    #[ORM\Column]
    #[Assert\Range(
        min: 0,
        max: 100,
        notInRangeMessage: 'Le score d\'engagement doit être entre {{ min }} et {{ max }}'
    )]
    private ?int $engagementScore = 0;

    /**
     * Array of difficulty signals detected
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $difficultySignals = null;

    /**
     * Whether student is flagged as at risk of dropout
     */
    #[ORM\Column]
    private ?bool $atRiskOfDropout = false;

    /**
     * Risk score calculation (0-100, higher = more risk)
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private ?string $riskScore = '0.00';

    /**
     * Total time spent on the training (in minutes)
     */
    #[ORM\Column]
    private ?int $totalTimeSpent = 0;

    /**
     * Number of login sessions
     */
    #[ORM\Column]
    private ?int $loginCount = 0;

    /**
     * Average session duration (in minutes)
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    private ?string $averageSessionDuration = null;

    /**
     * Attendance rate for sessions (0-100)
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private ?string $attendanceRate = '100.00';

    /**
     * Number of missed sessions
     */
    #[ORM\Column]
    private ?int $missedSessions = 0;

    /**
     * Date when training was started
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $startedAt = null;

    /**
     * Date when training was completed (if applicable)
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    /**
     * Last risk assessment date
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastRiskAssessment = null;

    // Alternance-specific fields
    #[ORM\ManyToOne(targetEntity: AlternanceContract::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?AlternanceContract $alternanceContract = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Assert\Range(
        min: 0,
        max: 100,
        notInRangeMessage: 'Le taux de completion centre doit être entre {{ min }}% et {{ max }}%'
    )]
    private ?string $centerCompletionRate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Assert\Range(
        min: 0,
        max: 100,
        notInRangeMessage: 'Le taux de completion entreprise doit être entre {{ min }}% et {{ max }}%'
    )]
    private ?string $companyCompletionRate = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $missionProgress = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $skillsAcquired = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Choice(
        choices: self::ALTERNANCE_STATUS_OPTIONS,
        message: 'Statut d\'alternance invalide'
    )]
    private ?string $alternanceStatus = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Range(
        min: 0,
        max: 100,
        notInRangeMessage: 'Le score de risque alternance doit être entre {{ min }} et {{ max }}'
    )]
    private ?int $alternanceRiskScore = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->startedAt = new \DateTime();
        $this->lastActivity = new \DateTime();
        $this->moduleProgress = [];
        $this->chapterProgress = [];
        $this->difficultySignals = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStudent(): ?Student
    {
        return $this->student;
    }

    public function setStudent(?Student $student): static
    {
        $this->student = $student;
        return $this;
    }

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): static
    {
        $this->formation = $formation;
        return $this;
    }

    public function getCurrentModule(): ?Module
    {
        return $this->currentModule;
    }

    public function setCurrentModule(?Module $currentModule): static
    {
        $this->currentModule = $currentModule;
        return $this;
    }

    public function getCurrentChapter(): ?Chapter
    {
        return $this->currentChapter;
    }

    public function setCurrentChapter(?Chapter $currentChapter): static
    {
        $this->currentChapter = $currentChapter;
        return $this;
    }

    public function getCompletionPercentage(): ?string
    {
        return $this->completionPercentage;
    }

    public function setCompletionPercentage(string $completionPercentage): static
    {
        $this->completionPercentage = $completionPercentage;
        return $this;
    }

    public function getModuleProgress(): ?array
    {
        return $this->moduleProgress ?? [];
    }

    public function setModuleProgress(?array $moduleProgress): static
    {
        $this->moduleProgress = $moduleProgress;
        return $this;
    }

    public function getChapterProgress(): ?array
    {
        return $this->chapterProgress ?? [];
    }

    public function setChapterProgress(?array $chapterProgress): static
    {
        $this->chapterProgress = $chapterProgress;
        return $this;
    }

    public function getLastActivity(): ?\DateTimeInterface
    {
        return $this->lastActivity;
    }

    public function setLastActivity(\DateTimeInterface $lastActivity): static
    {
        $this->lastActivity = $lastActivity;
        return $this;
    }

    public function getEngagementScore(): ?int
    {
        return $this->engagementScore;
    }

    public function setEngagementScore(int $engagementScore): static
    {
        $this->engagementScore = max(0, min(100, $engagementScore));
        return $this;
    }

    public function getDifficultySignals(): ?array
    {
        return $this->difficultySignals ?? [];
    }

    public function setDifficultySignals(?array $difficultySignals): static
    {
        $this->difficultySignals = $difficultySignals;
        return $this;
    }

    public function isAtRiskOfDropout(): ?bool
    {
        return $this->atRiskOfDropout;
    }

    public function setAtRiskOfDropout(bool $atRiskOfDropout): static
    {
        $this->atRiskOfDropout = $atRiskOfDropout;
        return $this;
    }

    public function getRiskScore(): ?string
    {
        return $this->riskScore;
    }

    public function setRiskScore(string $riskScore): static
    {
        $this->riskScore = $riskScore;
        return $this;
    }

    public function getTotalTimeSpent(): ?int
    {
        return $this->totalTimeSpent;
    }

    public function setTotalTimeSpent(int $totalTimeSpent): static
    {
        $this->totalTimeSpent = max(0, $totalTimeSpent);
        return $this;
    }

    public function getLoginCount(): ?int
    {
        return $this->loginCount;
    }

    public function setLoginCount(int $loginCount): static
    {
        $this->loginCount = max(0, $loginCount);
        return $this;
    }

    public function getAverageSessionDuration(): ?string
    {
        return $this->averageSessionDuration;
    }

    public function setAverageSessionDuration(?string $averageSessionDuration): static
    {
        $this->averageSessionDuration = $averageSessionDuration;
        return $this;
    }

    public function getAttendanceRate(): ?string
    {
        return $this->attendanceRate;
    }

    public function setAttendanceRate(string $attendanceRate): static
    {
        $this->attendanceRate = $attendanceRate;
        return $this;
    }

    public function getMissedSessions(): ?int
    {
        return $this->missedSessions;
    }

    public function setMissedSessions(int $missedSessions): static
    {
        $this->missedSessions = max(0, $missedSessions);
        return $this;
    }

    public function getStartedAt(): ?\DateTimeInterface
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTimeInterface $startedAt): static
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeInterface $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getLastRiskAssessment(): ?\DateTimeInterface
    {
        return $this->lastRiskAssessment;
    }

    public function setLastRiskAssessment(?\DateTimeInterface $lastRiskAssessment): static
    {
        $this->lastRiskAssessment = $lastRiskAssessment;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * Update activity timestamp and recalculate engagement
     */
    public function updateActivity(): static
    {
        $this->lastActivity = new \DateTime();
        $this->loginCount++;
        $this->calculateEngagementScore();
        return $this;
    }

    /**
     * Add time spent to the total
     */
    public function addTimeSpent(int $minutes): static
    {
        $this->totalTimeSpent += $minutes;
        $this->calculateAverageSessionDuration();
        return $this;
    }

    /**
     * Mark a module as completed
     */
    public function completeModule(int $moduleId): static
    {
        $moduleProgress = $this->getModuleProgress();
        $moduleProgress[$moduleId] = [
            'completed' => true,
            'completedAt' => (new \DateTime())->format('Y-m-d H:i:s'),
            'percentage' => 100
        ];
        $this->setModuleProgress($moduleProgress);
        $this->updateProgress();
        return $this;
    }

    /**
     * Mark a chapter as completed
     */
    public function completeChapter(int $chapterId): static
    {
        $chapterProgress = $this->getChapterProgress();
        $chapterProgress[$chapterId] = [
            'completed' => true,
            'completedAt' => (new \DateTime())->format('Y-m-d H:i:s'),
            'percentage' => 100
        ];
        $this->setChapterProgress($chapterProgress);
        $this->updateProgress();
        return $this;
    }

    /**
     * Update module progress percentage
     */
    public function updateModuleProgress(int $moduleId, float $percentage): static
    {
        $moduleProgress = $this->getModuleProgress();
        $moduleProgress[$moduleId] = [
            'completed' => $percentage >= 100,
            'percentage' => min(100, max(0, $percentage)),
            'lastUpdated' => (new \DateTime())->format('Y-m-d H:i:s')
        ];
        
        if ($percentage >= 100) {
            $moduleProgress[$moduleId]['completedAt'] = (new \DateTime())->format('Y-m-d H:i:s');
        }
        
        $this->setModuleProgress($moduleProgress);
        $this->updateProgress();
        return $this;
    }

    /**
     * Update chapter progress percentage
     */
    public function updateChapterProgress(int $chapterId, float $percentage): static
    {
        $chapterProgress = $this->getChapterProgress();
        $chapterProgress[$chapterId] = [
            'completed' => $percentage >= 100,
            'percentage' => min(100, max(0, $percentage)),
            'lastUpdated' => (new \DateTime())->format('Y-m-d H:i:s')
        ];
        
        if ($percentage >= 100) {
            $chapterProgress[$chapterId]['completedAt'] = (new \DateTime())->format('Y-m-d H:i:s');
        }
        
        $this->setChapterProgress($chapterProgress);
        $this->updateProgress();
        return $this;
    }

    /**
     * Calculate overall progress from modules and chapters
     */
    public function updateProgress(): static
    {
        // This would need to be implemented with actual Formation/Module/Chapter data
        // For now, calculate based on completed modules/chapters
        $moduleProgress = $this->getModuleProgress();
        $chapterProgress = $this->getChapterProgress();
        
        $totalItems = count($moduleProgress) + count($chapterProgress);
        if ($totalItems === 0) {
            $this->completionPercentage = '0.00';
            return $this;
        }
        
        $completedItems = 0;
        foreach ($moduleProgress as $progress) {
            if ($progress['completed'] ?? false) {
                $completedItems++;
            }
        }
        
        foreach ($chapterProgress as $progress) {
            if ($progress['completed'] ?? false) {
                $completedItems++;
            }
        }
        
        $percentage = ($completedItems / $totalItems) * 100;
        $this->completionPercentage = number_format($percentage, 2);
        
        // Mark as completed if 100%
        if ($percentage >= 100 && !$this->completedAt) {
            $this->completedAt = new \DateTime();
        }
        
        return $this;
    }

    /**
     * Calculate engagement score based on various factors
     */
    public function calculateEngagementScore(): int
    {
        $score = 0;
        
        // Recent activity (0-30 points)
        if ($this->lastActivity) {
            $daysSinceLastActivity = (new \DateTime())->diff($this->lastActivity)->days;
            if ($daysSinceLastActivity === 0) {
                $score += 30;
            } elseif ($daysSinceLastActivity <= 2) {
                $score += 25;
            } elseif ($daysSinceLastActivity <= 7) {
                $score += 15;
            } elseif ($daysSinceLastActivity <= 14) {
                $score += 5;
            }
        }
        
        // Attendance rate (0-25 points)
        $attendanceRate = (float) $this->attendanceRate;
        $score += (int) ($attendanceRate * 0.25);
        
        // Progress completion (0-25 points)
        $completionRate = (float) $this->completionPercentage;
        $score += (int) ($completionRate * 0.25);
        
        // Login frequency (0-20 points)
        if ($this->startedAt) {
            $daysSinceStart = max(1, (new \DateTime())->diff($this->startedAt)->days);
            $averageLoginsPerDay = $this->loginCount / $daysSinceStart;
            if ($averageLoginsPerDay >= 1) {
                $score += 20;
            } elseif ($averageLoginsPerDay >= 0.5) {
                $score += 15;
            } elseif ($averageLoginsPerDay >= 0.2) {
                $score += 10;
            }
        }
        
        $this->engagementScore = min(100, max(0, $score));
        return $this->engagementScore;
    }

    /**
     * Detect risk signals and update risk status
     */
    public function detectRiskSignals(): array
    {
        $signals = [];
        
        // Low engagement
        if ($this->engagementScore < 30) {
            $signals[] = 'low_engagement';
        }
        
        // Inactivity
        if ($this->lastActivity) {
            $daysSinceLastActivity = (new \DateTime())->diff($this->lastActivity)->days;
            if ($daysSinceLastActivity > 7) {
                $signals[] = 'prolonged_inactivity';
            }
        }
        
        // Poor attendance
        if ((float) $this->attendanceRate < 70) {
            $signals[] = 'poor_attendance';
        }
        
        // Slow progress
        if ($this->startedAt) {
            $daysSinceStart = (new \DateTime())->diff($this->startedAt)->days;
            $expectedProgress = min(100, ($daysSinceStart / 30) * 50); // Rough estimate
            if ((float) $this->completionPercentage < $expectedProgress * 0.5) {
                $signals[] = 'slow_progress';
            }
        }
        
        // Multiple missed sessions
        if ($this->missedSessions >= 3) {
            $signals[] = 'frequent_absences';
        }
        
        $this->difficultySignals = $signals;
        $this->atRiskOfDropout = count($signals) >= 2;
        $this->lastRiskAssessment = new \DateTime();
        
        // Calculate risk score
        $riskScore = count($signals) * 20; // Each signal adds 20% risk
        $this->riskScore = number_format(min(100, $riskScore), 2);
        
        return $signals;
    }

    /**
     * Calculate average session duration
     */
    private function calculateAverageSessionDuration(): void
    {
        if ($this->loginCount > 0) {
            $average = $this->totalTimeSpent / $this->loginCount;
            $this->averageSessionDuration = number_format($average, 2);
        }
    }

    /**
     * Record a missed session
     */
    public function recordMissedSession(): static
    {
        $this->missedSessions++;
        
        // Recalculate attendance rate (this would need actual session data)
        // For now, estimate based on missed sessions
        $totalSessions = $this->missedSessions + 10; // Rough estimate
        $attendedSessions = $totalSessions - $this->missedSessions;
        $this->attendanceRate = number_format(($attendedSessions / $totalSessions) * 100, 2);
        
        $this->detectRiskSignals();
        return $this;
    }

    /**
     * Add a difficulty signal
     */
    public function addDifficultySignal(string $signal): static
    {
        $signals = $this->getDifficultySignals();
        if (!in_array($signal, $signals)) {
            $signals[] = $signal;
            $this->setDifficultySignals($signals);
            $this->detectRiskSignals();
        }
        return $this;
    }

    /**
     * Remove a difficulty signal
     */
    public function removeDifficultySignal(string $signal): static
    {
        $signals = $this->getDifficultySignals();
        $key = array_search($signal, $signals);
        if ($key !== false) {
            unset($signals[$key]);
            $this->setDifficultySignals(array_values($signals));
            $this->detectRiskSignals();
        }
        return $this;
    }

    /**
     * Check if training is completed
     */
    public function isCompleted(): bool
    {
        return $this->completedAt !== null;
    }

    /**
     * Get completion status label
     */
    public function getCompletionStatusLabel(): string
    {
        if ($this->isCompleted()) {
            return 'Terminé';
        }
        
        $percentage = (float) $this->completionPercentage;
        if ($percentage === 0.0) {
            return 'Non démarré';
        } elseif ($percentage < 25) {
            return 'Débutant';
        } elseif ($percentage < 50) {
            return 'En progression';
        } elseif ($percentage < 75) {
            return 'Avancé';
        } else {
            return 'Presque terminé';
        }
    }

    /**
     * Get engagement status badge class
     */
    public function getEngagementBadgeClass(): string
    {
        if ($this->engagementScore >= 80) {
            return 'bg-success';
        } elseif ($this->engagementScore >= 60) {
            return 'bg-warning';
        } else {
            return 'bg-danger';
        }
    }

    /**
     * Get risk level label
     */
    public function getRiskLevelLabel(): string
    {
        $riskScore = (float) $this->riskScore;
        if ($riskScore < 20) {
            return 'Faible';
        } elseif ($riskScore < 40) {
            return 'Modéré';
        } elseif ($riskScore < 60) {
            return 'Élevé';
        } else {
            return 'Critique';
        }
    }

    /**
     * Lifecycle callback to update the updatedAt timestamp
     */
    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Alternance-specific methods

    public function getAlternanceContract(): ?AlternanceContract
    {
        return $this->alternanceContract;
    }

    public function setAlternanceContract(?AlternanceContract $alternanceContract): static
    {
        $this->alternanceContract = $alternanceContract;
        return $this;
    }

    public function getCenterCompletionRate(): ?string
    {
        return $this->centerCompletionRate;
    }

    public function setCenterCompletionRate(?string $centerCompletionRate): static
    {
        $this->centerCompletionRate = $centerCompletionRate;
        return $this;
    }

    public function getCompanyCompletionRate(): ?string
    {
        return $this->companyCompletionRate;
    }

    public function setCompanyCompletionRate(?string $companyCompletionRate): static
    {
        $this->companyCompletionRate = $companyCompletionRate;
        return $this;
    }

    public function getMissionProgress(): ?array
    {
        return $this->missionProgress ?? [];
    }

    public function setMissionProgress(?array $missionProgress): static
    {
        $this->missionProgress = $missionProgress;
        return $this;
    }

    public function getSkillsAcquired(): ?array
    {
        return $this->skillsAcquired ?? [];
    }

    public function setSkillsAcquired(?array $skillsAcquired): static
    {
        $this->skillsAcquired = $skillsAcquired;
        return $this;
    }

    public function getAlternanceStatus(): ?string
    {
        return $this->alternanceStatus;
    }

    public function setAlternanceStatus(?string $alternanceStatus): static
    {
        $this->alternanceStatus = $alternanceStatus;
        return $this;
    }

    public function getAlternanceRiskScore(): ?int
    {
        return $this->alternanceRiskScore;
    }

    public function setAlternanceRiskScore(?int $alternanceRiskScore): static
    {
        $this->alternanceRiskScore = $alternanceRiskScore;
        return $this;
    }

    /**
     * Update alternance progress from contract and missions data
     */
    public function updateAlternanceProgress(): void
    {
        if (!$this->alternanceContract) {
            return;
        }

        // Calculate center completion rate based on formation progress
        $this->centerCompletionRate = $this->completionPercentage;

        // Calculate company completion rate based on mission progress
        $missionProgress = $this->getMissionProgress();
        if (!empty($missionProgress)) {
            $totalMissions = count($missionProgress);
            $completedMissions = 0;
            
            foreach ($missionProgress as $mission) {
                if (($mission['completion_rate'] ?? 0) >= 100) {
                    $completedMissions++;
                }
            }
            
            $this->companyCompletionRate = number_format(($completedMissions / $totalMissions) * 100, 2);
        } else {
            $this->companyCompletionRate = '0.00';
        }

        // Update alternance status based on progress
        $this->updateAlternanceStatus();
        
        // Calculate alternance-specific risk score
        $this->calculateAlternanceRiskScore();
    }

    /**
     * Calculate alternance engagement score
     */
    public function calculateAlternanceEngagement(): int
    {
        $score = 0;
        
        // Base engagement score (50% weight)
        $score += (int) ($this->engagementScore * 0.5);
        
        // Mission completion rate (30% weight)
        $missionProgress = $this->getMissionProgress();
        if (!empty($missionProgress)) {
            $avgCompletionRate = 0;
            foreach ($missionProgress as $mission) {
                $avgCompletionRate += ($mission['completion_rate'] ?? 0);
            }
            $avgCompletionRate /= count($missionProgress);
            $score += (int) (($avgCompletionRate / 100) * 30);
        }
        
        // Skills acquisition progress (20% weight)
        $skillsAcquired = $this->getSkillsAcquired();
        if (!empty($skillsAcquired)) {
            $skillsCount = count($skillsAcquired);
            $masteredSkills = 0;
            
            foreach ($skillsAcquired as $skill) {
                if (($skill['level'] ?? 0) >= 16) { // 80% mastery
                    $masteredSkills++;
                }
            }
            
            $masteryRate = $skillsCount > 0 ? ($masteredSkills / $skillsCount) : 0;
            $score += (int) ($masteryRate * 20);
        }
        
        return min(100, max(0, $score));
    }

    /**
     * Get alternance risk factors
     */
    public function getAlternanceRiskFactors(): array
    {
        $factors = [];
        
        // Low center completion rate
        if ((float) ($this->centerCompletionRate ?? 0) < 50) {
            $factors[] = [
                'factor' => 'Retard en formation centre',
                'severity' => 'high',
                'description' => 'Progression en centre de formation inférieure à 50%'
            ];
        }
        
        // Low company completion rate
        if ((float) ($this->companyCompletionRate ?? 0) < 50) {
            $factors[] = [
                'factor' => 'Retard en entreprise',
                'severity' => 'high',
                'description' => 'Progression en entreprise inférieure à 50%'
            ];
        }
        
        // Unbalanced progression
        $centerRate = (float) ($this->centerCompletionRate ?? 0);
        $companyRate = (float) ($this->companyCompletionRate ?? 0);
        if (abs($centerRate - $companyRate) > 30) {
            $factors[] = [
                'factor' => 'Déséquilibre centre-entreprise',
                'severity' => 'medium',
                'description' => 'Écart important entre progression centre et entreprise'
            ];
        }
        
        // Few skills acquired
        $skillsAcquired = $this->getSkillsAcquired();
        if (count($skillsAcquired) < 5) {
            $factors[] = [
                'factor' => 'Acquisition de compétences faible',
                'severity' => 'medium',
                'description' => 'Nombre de compétences acquises insuffisant'
            ];
        }
        
        return $factors;
    }

    /**
     * Get skills acquisition rate
     */
    public function getSkillsAcquisitionRate(): float
    {
        $skillsAcquired = $this->getSkillsAcquired();
        
        if (empty($skillsAcquired)) {
            return 0.0;
        }
        
        $totalSkills = count($skillsAcquired);
        $masteredSkills = 0;
        
        foreach ($skillsAcquired as $skill) {
            if (($skill['level'] ?? 0) >= 16) { // 80% mastery threshold
                $masteredSkills++;
            }
        }
        
        return ($masteredSkills / $totalSkills) * 100;
    }

    /**
     * Update alternance status based on various factors
     */
    private function updateAlternanceStatus(): void
    {
        if (!$this->alternanceContract) {
            return;
        }
        
        $centerRate = (float) ($this->centerCompletionRate ?? 0);
        $companyRate = (float) ($this->companyCompletionRate ?? 0);
        $overallRate = ($centerRate + $companyRate) / 2;
        
        // Determine status based on progress and risk factors
        if ($overallRate >= 95) {
            $this->alternanceStatus = 'completed';
        } elseif ($this->alternanceRiskScore >= 70) {
            $this->alternanceStatus = 'at_risk';
        } elseif ($this->alternanceRiskScore >= 50) {
            $this->alternanceStatus = 'needs_support';
        } elseif ($overallRate < 10) {
            $this->alternanceStatus = 'paused';
        } else {
            $this->alternanceStatus = 'active';
        }
    }

    /**
     * Calculate alternance-specific risk score
     */
    private function calculateAlternanceRiskScore(): void
    {
        $riskScore = 0;
        
        // Base risk from general student progress
        $riskScore += (float) $this->riskScore;
        
        // Additional risk factors specific to alternance
        $factors = $this->getAlternanceRiskFactors();
        foreach ($factors as $factor) {
            switch ($factor['severity']) {
                case 'high':
                    $riskScore += 20;
                    break;
                case 'medium':
                    $riskScore += 10;
                    break;
                case 'low':
                    $riskScore += 5;
                    break;
            }
        }
        
        $this->alternanceRiskScore = (int) min(100, max(0, $riskScore));
    }

    /**
     * Add mission progress
     */
    public function addMissionProgress(int $missionId, string $missionTitle, float $completionRate, ?string $status = null): static
    {
        $missionProgress = $this->getMissionProgress();
        
        $missionProgress[$missionId] = [
            'title' => $missionTitle,
            'completion_rate' => $completionRate,
            'status' => $status ?? 'en_cours',
            'last_updated' => (new \DateTime())->format('Y-m-d H:i:s')
        ];
        
        $this->setMissionProgress($missionProgress);
        $this->updateAlternanceProgress();
        
        return $this;
    }

    /**
     * Add acquired skill
     */
    public function addAcquiredSkill(string $skillCode, string $skillName, float $level, ?string $context = null): static
    {
        $skillsAcquired = $this->getSkillsAcquired();
        
        $skillsAcquired[$skillCode] = [
            'name' => $skillName,
            'level' => $level,
            'context' => $context ?? 'general',
            'acquired_at' => (new \DateTime())->format('Y-m-d H:i:s')
        ];
        
        $this->setSkillsAcquired($skillsAcquired);
        
        return $this;
    }

    /**
     * Get alternance status label
     */
    public function getAlternanceStatusLabel(): string
    {
        return self::ALTERNANCE_STATUS_OPTIONS[$this->alternanceStatus] ?? $this->alternanceStatus ?? 'Non défini';
    }

    /**
     * Get alternance status badge class
     */
    public function getAlternanceStatusBadgeClass(): string
    {
        return match ($this->alternanceStatus) {
            'active' => 'badge-success',
            'completed' => 'badge-primary',
            'paused' => 'badge-warning',
            'terminated' => 'badge-danger',
            'at_risk' => 'badge-danger',
            'needs_support' => 'badge-warning',
            default => 'badge-secondary'
        };
    }

    /**
     * Check if student is in alternance program
     */
    public function isInAlternance(): bool
    {
        return $this->alternanceContract !== null;
    }

    public function __toString(): string
    {
        return sprintf(
            '%s - %s (%s%%)',
            $this->student?->getFullName() ?? 'Étudiant inconnu',
            $this->formation?->getTitle() ?? 'Formation inconnue',
            $this->completionPercentage
        );
    }
}
