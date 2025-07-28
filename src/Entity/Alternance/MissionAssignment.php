<?php

namespace App\Entity\Alternance;

use App\Entity\User\Student;
use App\Repository\Alternance\MissionAssignmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * MissionAssignment entity representing the assignment of a mission to a student
 * 
 * Tracks the progress, feedback, and evaluation of missions assigned to apprentices
 * with full traceability for Qualiopi compliance.
 */
#[ORM\Entity(repositoryClass: MissionAssignmentRepository::class)]
#[ORM\Table(name: 'mission_assignments')]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\Loggable]
class MissionAssignment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Student::class, inversedBy: 'missionAssignments')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'L\'alternant est obligatoire.')]
    private ?Student $student = null;

    #[ORM\ManyToOne(targetEntity: CompanyMission::class, inversedBy: 'assignments')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'La mission est obligatoire.')]
    private ?CompanyMission $mission = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'La date de début est obligatoire.')]
    #[Gedmo\Versioned]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'La date de fin prévue est obligatoire.')]
    #[Assert\GreaterThan(
        propertyPath: 'startDate',
        message: 'La date de fin doit être postérieure à la date de début.'
    )]
    #[Gedmo\Versioned]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: ['planifiee', 'en_cours', 'terminee', 'suspendue'],
        message: 'Statut de mission invalide.'
    )]
    #[Gedmo\Versioned]
    private ?string $status = 'planifiee';

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'Les objectifs intermédiaires sont obligatoires.')]
    #[Gedmo\Versioned]
    private array $intermediateObjectives = [];

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    #[Assert\NotNull(message: 'Le taux d\'avancement est obligatoire.')]
    #[Assert\Range(
        min: 0,
        max: 100,
        notInRangeMessage: 'Le taux d\'avancement doit être entre {{ min }} et {{ max }}%.'
    )]
    #[Gedmo\Versioned]
    private ?string $completionRate = '0.00';

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'Les difficultés rencontrées doivent être documentées.')]
    #[Gedmo\Versioned]
    private array $difficulties = [];

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'Les réalisations doivent être documentées.')]
    #[Gedmo\Versioned]
    private array $achievements = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $mentorFeedback = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $studentFeedback = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Range(
        min: 1,
        max: 10,
        notInRangeMessage: 'La note du tuteur doit être entre {{ min }} et {{ max }}.'
    )]
    #[Gedmo\Versioned]
    private ?int $mentorRating = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Range(
        min: 1,
        max: 10,
        notInRangeMessage: 'La satisfaction de l\'alternant doit être entre {{ min }} et {{ max }}.'
    )]
    #[Gedmo\Versioned]
    private ?int $studentSatisfaction = null;

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'Les compétences acquises doivent être documentées.')]
    #[Gedmo\Versioned]
    private array $competenciesAcquired = [];

    #[ORM\Column]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeImmutable $lastUpdated = null;

    /**
     * Available statuses for mission assignments
     */
    public const STATUSES = [
        'planifiee' => 'Planifiée',
        'en_cours' => 'En cours',
        'terminee' => 'Terminée',
        'suspendue' => 'Suspendue'
    ];

    /**
     * Rating scale labels
     */
    public const RATING_LABELS = [
        1 => 'Très insuffisant',
        2 => 'Insuffisant', 
        3 => 'Passable',
        4 => 'Correct',
        5 => 'Bien',
        6 => 'Très bien',
        7 => 'Excellent',
        8 => 'Remarquable',
        9 => 'Exceptionnel',
        10 => 'Parfait'
    ];

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->lastUpdated = new \DateTimeImmutable();
        
        // Initialize array fields to prevent null reference errors
        $this->intermediateObjectives = [];
        $this->difficulties = [];
        $this->achievements = [];
        $this->competenciesAcquired = [];
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

    public function getMission(): ?CompanyMission
    {
        return $this->mission;
    }

    public function setMission(?CompanyMission $mission): static
    {
        $this->mission = $mission;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getIntermediateObjectives(): array
    {
        return $this->intermediateObjectives;
    }

    public function setIntermediateObjectives(array $intermediateObjectives): static
    {
        $this->intermediateObjectives = $intermediateObjectives;
        return $this;
    }

    /**
     * Get intermediate objectives as DTO objects for form usage
     * 
     * @return \App\DTO\Alternance\IntermediateObjectiveDTO[]
     */
    public function getIntermediateObjectivesForForm(): array
    {
        $dtos = [];
        foreach ($this->intermediateObjectives as $index => $objectiveData) {
            if (is_array($objectiveData)) {
                // Check if this is a legacy format where keys might be wrong
                if (isset($objectiveData[0]) && is_string($objectiveData[0])) {
                    // This might be an indexed array instead of associative
                    $dtos[] = new \App\DTO\Alternance\IntermediateObjectiveDTO(
                        title: $objectiveData[0] ?? 'Objectif sans titre',
                        description: isset($objectiveData[1]) && is_string($objectiveData[1]) ? $objectiveData[1] : '',
                        completed: isset($objectiveData[2]) ? (bool) $objectiveData[2] : false
                    );
                } else {
                    // Normal associative array
                    $dtos[] = \App\DTO\Alternance\IntermediateObjectiveDTO::fromArray($objectiveData);
                }
            } elseif (is_string($objectiveData)) {
                // Handle legacy simple string objectives
                $dtos[] = new \App\DTO\Alternance\IntermediateObjectiveDTO(
                    title: $objectiveData,
                    description: '',
                    completed: false
                );
            } else {
                // Handle any other data types by creating a default objective
                $dtos[] = new \App\DTO\Alternance\IntermediateObjectiveDTO(
                    title: 'Objectif ' . ($index + 1),
                    description: '',
                    completed: false
                );
            }
        }
        return $dtos;
    }

    /**
     * Set intermediate objectives from DTO objects for form usage
     * 
     * @param \App\DTO\Alternance\IntermediateObjectiveDTO[] $dtos
     */
    public function setIntermediateObjectivesForForm(array $dtos): static
    {
        $arrayData = [];
        foreach ($dtos as $dto) {
            if ($dto instanceof \App\DTO\Alternance\IntermediateObjectiveDTO) {
                // Only include objectives with non-empty titles
                if (!empty(trim($dto->title))) {
                    $arrayData[] = $dto->toArray();
                }
            }
        }
        $this->intermediateObjectives = $arrayData;
        return $this;
    }

    /**
     * Get intermediate objectives as DTO objects for display
     * 
     * @return \App\DTO\Alternance\IntermediateObjectiveDTO[]
     */
    public function getIntermediateObjectivesAsDTO(): array
    {
        return $this->getIntermediateObjectivesForForm();
    }

    public function getCompletionRate(): ?float
    {
        return $this->completionRate !== null ? (float) $this->completionRate : null;
    }

    public function setCompletionRate(float $completionRate): static
    {
        $this->completionRate = (string) $completionRate;
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

    public function getAchievements(): array
    {
        return $this->achievements;
    }

    public function setAchievements(array $achievements): static
    {
        $this->achievements = $achievements;
        return $this;
    }

    public function getMentorFeedback(): ?string
    {
        return $this->mentorFeedback;
    }

    public function setMentorFeedback(?string $mentorFeedback): static
    {
        $this->mentorFeedback = $mentorFeedback;
        return $this;
    }

    public function getStudentFeedback(): ?string
    {
        return $this->studentFeedback;
    }

    public function setStudentFeedback(?string $studentFeedback): static
    {
        $this->studentFeedback = $studentFeedback;
        return $this;
    }

    public function getMentorRating(): ?int
    {
        return $this->mentorRating;
    }

    public function setMentorRating(?int $mentorRating): static
    {
        $this->mentorRating = $mentorRating;
        return $this;
    }

    public function getStudentSatisfaction(): ?int
    {
        return $this->studentSatisfaction;
    }

    public function setStudentSatisfaction(?int $studentSatisfaction): static
    {
        $this->studentSatisfaction = $studentSatisfaction;
        return $this;
    }

    public function getCompetenciesAcquired(): array
    {
        return $this->competenciesAcquired;
    }

    public function setCompetenciesAcquired(array $competenciesAcquired): static
    {
        $this->competenciesAcquired = $competenciesAcquired;
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

    public function getLastUpdated(): ?\DateTimeImmutable
    {
        return $this->lastUpdated;
    }

    public function setLastUpdated(\DateTimeImmutable $lastUpdated): static
    {
        $this->lastUpdated = $lastUpdated;
        return $this;
    }

    /**
     * Get the status as human-readable label
     */
    public function getStatusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Get mentor rating as human-readable label
     */
    public function getMentorRatingLabel(): string
    {
        if ($this->mentorRating === null) {
            return 'Non évalué';
        }
        
        return $this->mentorRating . '/10 - ' . (self::RATING_LABELS[$this->mentorRating] ?? 'Évaluation inconnue');
    }

    /**
     * Get student satisfaction as human-readable label
     */
    public function getStudentSatisfactionLabel(): string
    {
        if ($this->studentSatisfaction === null) {
            return 'Non évalué';
        }
        
        return $this->studentSatisfaction . '/10 - ' . (self::RATING_LABELS[$this->studentSatisfaction] ?? 'Évaluation inconnue');
    }

    /**
     * Check if the assignment is active (planned or in progress)
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['planifiee', 'en_cours']);
    }

    /**
     * Check if the assignment is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'terminee';
    }

    /**
     * Check if the assignment is suspended
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspendue';
    }

    /**
     * Check if the assignment is overdue
     */
    public function isOverdue(): bool
    {
        if ($this->status === 'terminee' || $this->endDate === null) {
            return false;
        }
        
        return $this->endDate < new \DateTime();
    }

    /**
     * Get the duration of the assignment in days
     */
    public function getDurationInDays(): int
    {
        if ($this->startDate === null || $this->endDate === null) {
            return 0;
        }
        
        return $this->startDate->diff($this->endDate)->days;
    }

    /**
     * Get the elapsed time since start in days
     */
    public function getElapsedDays(): int
    {
        if ($this->startDate === null) {
            return 0;
        }
        
        $now = new \DateTime();
        if ($this->startDate > $now) {
            return 0; // Not started yet
        }
        
        return $this->startDate->diff($now)->days;
    }

    /**
     * Get the remaining days until end date
     */
    public function getRemainingDays(): int
    {
        if ($this->endDate === null || $this->status === 'terminee') {
            return 0;
        }
        
        $now = new \DateTime();
        if ($this->endDate < $now) {
            return 0; // Overdue
        }
        
        return $now->diff($this->endDate)->days;
    }

    /**
     * Calculate progress percentage based on time elapsed
     */
    public function getTimeProgressPercentage(): float
    {
        $totalDays = $this->getDurationInDays();
        if ($totalDays === 0) {
            return 0.0;
        }
        
        $elapsedDays = $this->getElapsedDays();
        return min(100.0, ($elapsedDays / $totalDays) * 100);
    }

    /**
     * Get completion status with percentage
     */
    public function getCompletionStatus(): string
    {
        return number_format($this->completionRate, 1) . '% terminé';
    }

    /**
     * Get the CSS class for the status badge
     */
    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'planifiee' => 'badge-info',
            'en_cours' => 'badge-warning',
            'terminee' => 'badge-success',
            'suspendue' => 'badge-danger',
            default => 'badge-secondary'
        };
    }

    /**
     * Get the CSS class for the completion rate progress bar
     */
    public function getCompletionProgressClass(): string
    {
        if ($this->completionRate >= 80) {
            return 'progress-bar-success';
        } elseif ($this->completionRate >= 50) {
            return 'progress-bar-warning';
        } else {
            return 'progress-bar-danger';
        }
    }

    /**
     * Check if both mentor and student have provided feedback
     */
    public function hasBidirectionalFeedback(): bool
    {
        return !empty($this->mentorFeedback) && !empty($this->studentFeedback);
    }

    /**
     * Check if the assignment has been evaluated by the mentor
     */
    public function isEvaluatedByMentor(): bool
    {
        return $this->mentorRating !== null && !empty($this->mentorFeedback);
    }

    /**
     * Check if the student has provided satisfaction feedback
     */
    public function hasStudentFeedback(): bool
    {
        return $this->studentSatisfaction !== null && !empty($this->studentFeedback);
    }

    /**
     * Get a summary of intermediate objectives completion
     */
    public function getObjectivesCompletionSummary(): string
    {
        if (empty($this->intermediateObjectives)) {
            return 'Aucun objectif intermédiaire défini';
        }
        
        $completed = 0;
        foreach ($this->intermediateObjectives as $objective) {
            if (is_array($objective) && isset($objective['completed']) && $objective['completed'] === true) {
                $completed++;
            } elseif (is_string($objective)) {
                // Legacy objectives are considered not completed unless explicitly marked
                continue;
            }
        }
        
        $total = count($this->intermediateObjectives);
        return $completed . '/' . $total . ' objectifs atteints (' . round(($completed / $total) * 100) . '%)';
    }

    /**
     * Get a summary of competencies acquired
     */
    public function getCompetenciesSummary(): string
    {
        if (empty($this->competenciesAcquired)) {
            return 'Aucune compétence documentée';
        }
        
        $count = count($this->competenciesAcquired);
        if ($count <= 3) {
            return implode(', ', $this->competenciesAcquired);
        } else {
            $first3 = array_slice($this->competenciesAcquired, 0, 3);
            return implode(', ', $first3) . ' (+' . ($count - 3) . ' autres)';
        }
    }

    /**
     * Start the assignment (change status to in progress)
     */
    public function start(): void
    {
        if ($this->status === 'planifiee') {
            $this->status = 'en_cours';
            $this->lastUpdated = new \DateTimeImmutable();
        }
    }

    /**
     * Complete the assignment (change status to completed)
     */
    public function complete(): void
    {
        if (in_array($this->status, ['planifiee', 'en_cours'])) {
            $this->status = 'terminee';
            $this->completionRate = 100.0;
            $this->lastUpdated = new \DateTimeImmutable();
        }
    }

    /**
     * Suspend the assignment
     */
    public function suspend(): void
    {
        if (in_array($this->status, ['planifiee', 'en_cours'])) {
            $this->status = 'suspendue';
            $this->lastUpdated = new \DateTimeImmutable();
        }
    }

    /**
     * Resume a suspended assignment
     */
    public function resume(): void
    {
        if ($this->status === 'suspendue') {
            $this->status = 'en_cours';
            $this->lastUpdated = new \DateTimeImmutable();
        }
    }

    /**
     * Update completion rate and last updated timestamp
     */
    public function updateProgress(float $completionRate): void
    {
        $this->completionRate = max(0.0, min(100.0, $completionRate));
        $this->lastUpdated = new \DateTimeImmutable();
        
        // Auto-complete if 100%
        if ($this->completionRate >= 100.0 && $this->status !== 'terminee') {
            $this->complete();
        }
    }

    /**
     * Lifecycle callback to update the updatedAt timestamp
     */
    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
        $this->lastUpdated = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        $missionTitle = $this->mission ? $this->mission->getTitle() : 'Mission inconnue';
        $studentName = $this->student ? $this->student->getFullName() : 'Alternant inconnu';
        
        return $missionTitle . ' - ' . $studentName;
    }
}
