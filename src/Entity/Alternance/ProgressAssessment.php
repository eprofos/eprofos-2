<?php

declare(strict_types=1);

namespace App\Entity\Alternance;

use App\Entity\User\Student;
use App\Repository\Alternance\ProgressAssessmentRepository;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * ProgressAssessment entity for global progression tracking in alternance programs.
 *
 * This entity provides comprehensive tracking of student progression across both
 * training center and company environments, essential for Qualiopi compliance.
 */
#[ORM\Entity(repositoryClass: ProgressAssessmentRepository::class)]
#[ORM\Table(name: 'progress_assessments')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['student_id', 'period'], name: 'idx_student_period')]
#[Gedmo\Loggable]
#[ORM\Index(columns: ['risk_level'], name: 'idx_risk_level')]
#[ORM\Index(columns: ['overall_progression'], name: 'idx_overall_progression')]
class ProgressAssessment
{
    /**
     * Risk level constants.
     */
    public const RISK_LEVELS = [
        1 => 'Très faible',
        2 => 'Faible',
        3 => 'Modéré',
        4 => 'Élevé',
        5 => 'Critique',
    ];

    /**
     * Risk level colors.
     */
    public const RISK_LEVEL_COLORS = [
        1 => 'success',
        2 => 'info',
        3 => 'warning',
        4 => 'danger',
        5 => 'dark',
    ];

    /**
     * Standard objectives framework.
     */
    public const STANDARD_OBJECTIVES = [
        'technical' => [
            'name' => 'Objectifs techniques',
            'categories' => [
                'basic_skills' => 'Maîtrise des compétences de base',
                'advanced_skills' => 'Développement des compétences avancées',
                'tools_mastery' => 'Maîtrise des outils professionnels',
                'quality_standards' => 'Respect des standards qualité',
            ],
        ],
        'professional' => [
            'name' => 'Objectifs professionnels',
            'categories' => [
                'autonomy' => 'Développement de l\'autonomie',
                'responsibility' => 'Prise de responsabilités',
                'initiative' => 'Prise d\'initiatives',
                'problem_solving' => 'Résolution de problèmes',
            ],
        ],
        'transversal' => [
            'name' => 'Objectifs transversaux',
            'categories' => [
                'communication' => 'Communication professionnelle',
                'teamwork' => 'Travail en équipe',
                'time_management' => 'Gestion du temps',
                'adaptability' => 'Capacité d\'adaptation',
            ],
        ],
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Student::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'L\'alternant est obligatoire.')]
    private ?Student $student = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'La période d\'évaluation est obligatoire.')]
    #[Gedmo\Versioned]
    private ?DateTimeInterface $period = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    #[Assert\Range(
        min: 0,
        max: 100,
        notInRangeMessage: 'La progression centre doit être entre {{ min }}% et {{ max }}%',
    )]
    #[Gedmo\Versioned]
    private ?string $centerProgression = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    #[Assert\Range(
        min: 0,
        max: 100,
        notInRangeMessage: 'La progression entreprise doit être entre {{ min }}% et {{ max }}%',
    )]
    #[Gedmo\Versioned]
    private ?string $companyProgression = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    #[Assert\Range(
        min: 0,
        max: 100,
        notInRangeMessage: 'La progression globale doit être entre {{ min }}% et {{ max }}%',
    )]
    #[Gedmo\Versioned]
    private ?string $overallProgression = '0.00';

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'Les objectifs atteints sont obligatoires.')]
    #[Gedmo\Versioned]
    private array $completedObjectives = [];

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'Les objectifs en cours sont obligatoires.')]
    #[Gedmo\Versioned]
    private array $pendingObjectives = [];

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'Les objectifs à venir sont obligatoires.')]
    #[Gedmo\Versioned]
    private array $upcomingObjectives = [];

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'Les difficultés identifiées sont obligatoires.')]
    #[Gedmo\Versioned]
    private array $difficulties = [];

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'L\'accompagnement nécessaire est obligatoire.')]
    #[Gedmo\Versioned]
    private array $supportNeeded = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $nextSteps = null;

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'La matrice de compétences est obligatoire.')]
    #[Gedmo\Versioned]
    private array $skillsMatrix = [];

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Range(
        min: 1,
        max: 5,
        notInRangeMessage: 'Le niveau de risque doit être entre {{ min }} et {{ max }}',
    )]
    #[Gedmo\Versioned]
    private ?int $riskLevel = 1;

    #[ORM\Column]
    #[Gedmo\Timestampable(on: 'create')]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Gedmo\Timestampable(on: 'update')]
    private ?DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->period = new DateTime();
        $this->completedObjectives = [];
        $this->pendingObjectives = [];
        $this->upcomingObjectives = [];
        $this->difficulties = [];
        $this->supportNeeded = [];
        $this->skillsMatrix = [];
        $this->riskLevel = 1;
    }

    public function __toString(): string
    {
        return sprintf(
            '%s - %s (%.1f%%)',
            $this->student?->getFullName() ?? 'Alternant inconnu',
            $this->period?->format('m/Y') ?? '',
            (float) $this->overallProgression,
        );
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

    public function getPeriod(): ?DateTimeInterface
    {
        return $this->period;
    }

    public function setPeriod(DateTimeInterface $period): static
    {
        $this->period = $period;

        return $this;
    }

    public function getCenterProgression(): ?string
    {
        return $this->centerProgression;
    }

    public function setCenterProgression(string $centerProgression): static
    {
        $this->centerProgression = $centerProgression;

        return $this;
    }

    public function getCompanyProgression(): ?string
    {
        return $this->companyProgression;
    }

    public function setCompanyProgression(string $companyProgression): static
    {
        $this->companyProgression = $companyProgression;

        return $this;
    }

    public function getOverallProgression(): ?string
    {
        return $this->overallProgression;
    }

    public function setOverallProgression(string $overallProgression): static
    {
        $this->overallProgression = $overallProgression;

        return $this;
    }

    public function getCompletedObjectives(): array
    {
        return $this->completedObjectives;
    }

    public function setCompletedObjectives(array $completedObjectives): static
    {
        $this->completedObjectives = $completedObjectives;

        return $this;
    }

    public function getPendingObjectives(): array
    {
        return $this->pendingObjectives;
    }

    public function setPendingObjectives(array $pendingObjectives): static
    {
        $this->pendingObjectives = $pendingObjectives;

        return $this;
    }

    public function getUpcomingObjectives(): array
    {
        return $this->upcomingObjectives;
    }

    public function setUpcomingObjectives(array $upcomingObjectives): static
    {
        $this->upcomingObjectives = $upcomingObjectives;

        return $this;
    }

    public function getDifficulties(): array
    {
        return $this->difficulties;
    }

    public function setDifficulties(array $difficulties): static
    {
        $this->difficulties = $difficulties;

        return $this;
    }

    public function getSupportNeeded(): array
    {
        return $this->supportNeeded;
    }

    public function setSupportNeeded(array $supportNeeded): static
    {
        $this->supportNeeded = $supportNeeded;

        return $this;
    }

    public function getNextSteps(): ?string
    {
        return $this->nextSteps;
    }

    public function setNextSteps(?string $nextSteps): static
    {
        $this->nextSteps = $nextSteps;

        return $this;
    }

    public function getSkillsMatrix(): array
    {
        return $this->skillsMatrix;
    }

    public function setSkillsMatrix(array $skillsMatrix): static
    {
        $this->skillsMatrix = $skillsMatrix;

        return $this;
    }

    public function getRiskLevel(): ?int
    {
        return $this->riskLevel;
    }

    public function setRiskLevel(int $riskLevel): static
    {
        $this->riskLevel = max(1, min(5, $riskLevel));

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get risk level label.
     */
    public function getRiskLevelLabel(): string
    {
        return self::RISK_LEVELS[$this->riskLevel] ?? 'Inconnu';
    }

    /**
     * Get risk level color.
     */
    public function getRiskLevelColor(): string
    {
        return self::RISK_LEVEL_COLORS[$this->riskLevel] ?? 'secondary';
    }

    /**
     * Get risk level badge class.
     */
    public function getRiskLevelBadgeClass(): string
    {
        return 'badge-' . $this->getRiskLevelColor();
    }

    /**
     * Calculate overall progression from center and company progression.
     */
    public function calculateOverallProgression(): static
    {
        $centerProg = (float) $this->centerProgression;
        $companyProg = (float) $this->companyProgression;

        // Weighted average: 60% center, 40% company
        $overall = ($centerProg * 0.6) + ($companyProg * 0.4);
        $this->overallProgression = number_format($overall, 2);

        return $this;
    }

    /**
     * Get progression status.
     */
    public function getProgressionStatus(): string
    {
        $progression = (float) $this->overallProgression;

        if ($progression >= 90) {
            return 'excellent';
        }
        if ($progression >= 75) {
            return 'satisfactory';
        }
        if ($progression >= 50) {
            return 'average';
        }
        if ($progression >= 25) {
            return 'needs_improvement';
        }

        return 'critical';
    }

    /**
     * Get progression status label.
     */
    public function getProgressionStatusLabel(): string
    {
        return match ($this->getProgressionStatus()) {
            'excellent' => 'Excellent',
            'satisfactory' => 'Satisfaisant',
            'average' => 'Moyen',
            'needs_improvement' => 'À améliorer',
            'critical' => 'Critique',
            default => 'Inconnu'
        };
    }

    /**
     * Get progression status badge class.
     */
    public function getProgressionStatusBadgeClass(): string
    {
        return match ($this->getProgressionStatus()) {
            'excellent' => 'badge-success',
            'satisfactory' => 'badge-primary',
            'average' => 'badge-info',
            'needs_improvement' => 'badge-warning',
            'critical' => 'badge-danger',
            default => 'badge-secondary'
        };
    }

    /**
     * Add completed objective.
     */
    public function addCompletedObjective(string $category, string $objective, ?DateTimeInterface $completedAt = null): static
    {
        $this->completedObjectives[] = [
            'category' => $category,
            'objective' => $objective,
            'completed_at' => ($completedAt ?? new DateTime())->format('Y-m-d H:i:s'),
            'added_at' => (new DateTime())->format('Y-m-d H:i:s'),
        ];

        return $this;
    }

    /**
     * Add pending objective.
     */
    public function addPendingObjective(string $category, string $objective, ?string $targetDate = null, ?int $priority = null): static
    {
        $this->pendingObjectives[] = [
            'category' => $category,
            'objective' => $objective,
            'target_date' => $targetDate,
            'priority' => $priority ?? 3,
            'progress_percentage' => 0,
            'added_at' => (new DateTime())->format('Y-m-d H:i:s'),
        ];

        return $this;
    }

    /**
     * Add upcoming objective.
     */
    public function addUpcomingObjective(string $category, string $objective, ?string $startDate = null): static
    {
        $this->upcomingObjectives[] = [
            'category' => $category,
            'objective' => $objective,
            'start_date' => $startDate,
            'prerequisites' => [],
            'added_at' => (new DateTime())->format('Y-m-d H:i:s'),
        ];

        return $this;
    }

    /**
     * Add difficulty.
     */
    public function addDifficulty(string $area, string $description, int $severity = 3): static
    {
        $this->difficulties[] = [
            'area' => $area,
            'description' => $description,
            'severity' => max(1, min(5, $severity)),
            'identified_at' => (new DateTime())->format('Y-m-d H:i:s'),
            'status' => 'active',
        ];

        return $this;
    }

    /**
     * Add support needed.
     */
    public function addSupportNeeded(string $type, string $description, int $urgency = 3): static
    {
        $this->supportNeeded[] = [
            'type' => $type,
            'description' => $description,
            'urgency' => max(1, min(5, $urgency)),
            'requested_at' => (new DateTime())->format('Y-m-d H:i:s'),
            'status' => 'requested',
        ];

        return $this;
    }

    /**
     * Update skill in matrix.
     */
    public function updateSkillInMatrix(string $skillCode, string $skillName, float $level, ?string $lastAssessed = null): static
    {
        $this->skillsMatrix[$skillCode] = [
            'name' => $skillName,
            'level' => max(0, min(20, $level)), // Scale 0-20
            'last_assessed' => $lastAssessed ?? (new DateTime())->format('Y-m-d'),
            'progression_trend' => $this->calculateSkillTrend($skillCode, $level),
        ];

        return $this;
    }

    /**
     * Get objectives summary.
     */
    public function getObjectivesSummary(): array
    {
        return [
            'completed' => count($this->completedObjectives),
            'pending' => count($this->pendingObjectives),
            'upcoming' => count($this->upcomingObjectives),
            'total' => count($this->completedObjectives) + count($this->pendingObjectives) + count($this->upcomingObjectives),
            'completion_rate' => $this->calculateObjectivesCompletionRate(),
        ];
    }

    /**
     * Calculate objectives completion rate.
     */
    public function calculateObjectivesCompletionRate(): float
    {
        $total = count($this->completedObjectives) + count($this->pendingObjectives);

        if ($total === 0) {
            return 0.0;
        }

        return (count($this->completedObjectives) / $total) * 100;
    }

    /**
     * Get skills matrix summary.
     */
    public function getSkillsMatrixSummary(): array
    {
        if (empty($this->skillsMatrix)) {
            return [
                'total_skills' => 0,
                'average_level' => 0.0,
                'mastered_skills' => 0,
                'improving_skills' => 0,
                'declining_skills' => 0,
            ];
        }

        $totalLevel = 0;
        $masteredCount = 0;
        $improvingCount = 0;
        $decliningCount = 0;

        foreach ($this->skillsMatrix as $skill) {
            $level = $skill['level'] ?? 0;
            $totalLevel += $level;

            if ($level >= 16) { // 80% mastery
                $masteredCount++;
            }

            $trend = $skill['progression_trend'] ?? 'stable';
            if ($trend === 'improving') {
                $improvingCount++;
            } elseif ($trend === 'declining') {
                $decliningCount++;
            }
        }

        return [
            'total_skills' => count($this->skillsMatrix),
            'average_level' => $totalLevel / count($this->skillsMatrix),
            'mastered_skills' => $masteredCount,
            'improving_skills' => $improvingCount,
            'declining_skills' => $decliningCount,
        ];
    }

    /**
     * Calculate risk level based on various factors.
     */
    public function calculateRiskLevel(): static
    {
        $riskFactors = 0;

        // Low overall progression
        if ((float) $this->overallProgression < 50) {
            $riskFactors += 2;
        } elseif ((float) $this->overallProgression < 75) {
            $riskFactors++;
        }

        // High number of difficulties
        $severeDifficulties = array_filter($this->difficulties, static fn ($d) => ($d['severity'] ?? 3) >= 4);
        if (count($severeDifficulties) >= 2) {
            $riskFactors += 2;
        } elseif (count($this->difficulties) >= 3) {
            $riskFactors++;
        }

        // Support needed with high urgency
        $urgentSupport = array_filter($this->supportNeeded, static fn ($s) => ($s['urgency'] ?? 3) >= 4);
        if (count($urgentSupport) >= 1) {
            $riskFactors++;
        }

        // Low objectives completion rate
        $completionRate = $this->calculateObjectivesCompletionRate();
        if ($completionRate < 50) {
            $riskFactors++;
        }

        // Skills matrix issues
        $skillsSummary = $this->getSkillsMatrixSummary();
        if ($skillsSummary['declining_skills'] > $skillsSummary['improving_skills']) {
            $riskFactors++;
        }

        $this->riskLevel = min(5, max(1, $riskFactors + 1));

        return $this;
    }

    /**
     * Get risk factors analysis.
     */
    public function getRiskFactorsAnalysis(): array
    {
        $factors = [];

        if ((float) $this->overallProgression < 50) {
            $factors[] = [
                'factor' => 'Progression globale faible',
                'severity' => 'high',
                'description' => 'La progression globale est inférieure à 50%',
            ];
        }

        $severeDifficulties = array_filter($this->difficulties, static fn ($d) => ($d['severity'] ?? 3) >= 4);
        if (!empty($severeDifficulties)) {
            $factors[] = [
                'factor' => 'Difficultés importantes',
                'severity' => 'high',
                'description' => count($severeDifficulties) . ' difficulté(s) importante(s) identifiée(s)',
            ];
        }

        $urgentSupport = array_filter($this->supportNeeded, static fn ($s) => ($s['urgency'] ?? 3) >= 4);
        if (!empty($urgentSupport)) {
            $factors[] = [
                'factor' => 'Accompagnement urgent nécessaire',
                'severity' => 'medium',
                'description' => count($urgentSupport) . ' demande(s) d\'accompagnement urgent',
            ];
        }

        return $factors;
    }

    /**
     * Lifecycle callback to update the updatedAt timestamp.
     */
    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Get evaluation status based on progression.
     */
    public function getStatus(): string
    {
        $progression = (float) $this->overallProgression;

        if ($progression >= 80) {
            return 'validated';
        }
        if ($progression >= 50) {
            return 'pending';
        }

        return 'rejected';
    }

    /**
     * Get overall score as decimal for compatibility with controller.
     */
    public function getOverallScore(): ?float
    {
        return ((float) $this->overallProgression) / 100; // Convert from 0-100 to 0-1 scale
    }

    /**
     * Get mentor for compatibility with controller (from alternance contract).
     */
    public function getMentor()
    {
        // For now, return null - would need to implement relationship
        // if ($this->student && $this->student->getAlternanceContract()) {
        //     return $this->student->getAlternanceContract()->getMentor();
        // }
        return null;
    }

    /**
     * Get achieved objectives for template compatibility.
     */
    public function getAchievedObjectives(): array
    {
        return array_map(static fn ($obj) => $obj['objective'] ?? $obj, $this->completedObjectives);
    }

    /**
     * Get planned objectives for template compatibility.
     */
    public function getPlannedObjectives(): array
    {
        return array_map(static fn ($obj) => $obj['objective'] ?? $obj, $this->upcomingObjectives);
    }

    /**
     * Get total objectives count.
     */
    public function getTotalObjectives(): int
    {
        return count($this->completedObjectives) + count($this->pendingObjectives) + count($this->upcomingObjectives);
    }

    /**
     * Get period start date for template compatibility.
     */
    public function getPeriodStart(): ?DateTimeInterface
    {
        // Assume period is the start of the evaluation period
        if ($this->period) {
            $date = new DateTime($this->period->format('Y-m-d'));

            return $date->modify('first day of this month');
        }

        return null;
    }

    /**
     * Get period end date for template compatibility.
     */
    public function getPeriodEnd(): ?DateTimeInterface
    {
        // Assume period end is the last day of the month
        if ($this->period) {
            $date = new DateTime($this->period->format('Y-m-d'));

            return $date->modify('last day of this month');
        }

        return null;
    }

    /**
     * Get mentor comments for template compatibility.
     */
    public function getMentorComments(): ?string
    {
        // For now, return next steps as mentor comments
        return $this->nextSteps;
    }

    /**
     * Get student comments for template compatibility.
     */
    public function getStudentComments(): ?string
    {
        // Could be extracted from difficulties or support needed
        $comments = [];
        foreach ($this->difficulties as $difficulty) {
            if (isset($difficulty['description'])) {
                $comments[] = $difficulty['description'];
            }
        }

        return !empty($comments) ? implode("\n", $comments) : null;
    }

    /**
     * Get admin comments (placeholder for template compatibility).
     */
    public function getAdminComments(): ?string
    {
        return null; // Could be added later if needed
    }

    /**
     * Get validation date (placeholder for template compatibility).
     */
    public function getValidatedAt(): ?DateTimeImmutable
    {
        // Could track when the assessment was validated
        return null; // Could be added later if needed
    }

    /**
     * Get validator (placeholder for template compatibility).
     */
    public function getValidatedBy()
    {
        return null; // Could be added later if needed
    }

    /**
     * Get entity type for template compatibility.
     */
    public function getEntityType(): string
    {
        return 'progress';
    }

    /**
     * Calculate skill progression trend.
     */
    private function calculateSkillTrend(string $skillCode, float $newLevel): string
    {
        if (!isset($this->skillsMatrix[$skillCode])) {
            return 'new';
        }

        $previousLevel = $this->skillsMatrix[$skillCode]['level'] ?? 0;

        if ($newLevel > $previousLevel + 1) {
            return 'improving';
        }
        if ($newLevel < $previousLevel - 1) {
            return 'declining';
        }

        return 'stable';
    }
}
