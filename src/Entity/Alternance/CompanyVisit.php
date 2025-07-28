<?php

namespace App\Entity\Alternance;

use App\Entity\User\Mentor;
use App\Entity\User\Student;
use App\Entity\User\Teacher;
use App\Repository\Alternance\CompanyVisitRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * CompanyVisit entity for managing company visits by pedagogical supervisors
 * 
 * Represents visits to companies by training center staff to follow up apprentices,
 * essential for Qualiopi compliance regarding ongoing supervision and support.
 */
#[ORM\Entity(repositoryClass: CompanyVisitRepository::class)]
#[ORM\Table(name: 'company_visits')]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\Loggable]
#[ORM\Index(columns: ['student_id'], name: 'idx_visit_student')]
#[ORM\Index(columns: ['visit_date'], name: 'idx_visit_date')]
#[ORM\Index(columns: ['visit_type'], name: 'idx_visit_type')]
#[ORM\Index(columns: ['follow_up_required'], name: 'idx_visit_follow_up')]
class CompanyVisit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Student::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'L\'alternant visité est obligatoire.')]
    private ?Student $student = null;

    #[ORM\ManyToOne(targetEntity: Teacher::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: 'Le référent visiteur est obligatoire.')]
    private ?Teacher $visitor = null;

    #[ORM\ManyToOne(targetEntity: Mentor::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: 'Le tuteur rencontré est obligatoire.')]
    private ?Mentor $mentor = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'La date de visite est obligatoire.')]
    #[Gedmo\Versioned]
    private ?\DateTimeInterface $visitDate = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le type de visite est obligatoire.')]
    #[Assert\Choice(
        choices: [
            self::TYPE_FOLLOW_UP,
            self::TYPE_EVALUATION,
            self::TYPE_PROBLEM_SOLVING,
            self::TYPE_INTEGRATION,
            self::TYPE_FINAL_ASSESSMENT
        ],
        message: 'Type de visite invalide.'
    )]
    #[Gedmo\Versioned]
    private ?string $visitType = self::TYPE_FOLLOW_UP;

    #[ORM\Column(type: Types::JSON)]
    #[Assert\Type(type: 'array', message: 'Les objectifs vérifiés doivent être un tableau.')]
    #[Gedmo\Versioned]
    private array $objectivesChecked = [];

    #[ORM\Column(type: Types::JSON)]
    #[Assert\Type(type: 'array', message: 'Les activités observées doivent être un tableau.')]
    #[Gedmo\Versioned]
    private array $observedActivities = [];

    #[ORM\Column(type: Types::JSON)]
    #[Assert\Type(type: 'array', message: 'Les points forts doivent être un tableau.')]
    #[Gedmo\Versioned]
    private array $strengths = [];

    #[ORM\Column(type: Types::JSON)]
    #[Assert\Type(type: 'array', message: 'Les axes d\'amélioration doivent être un tableau.')]
    #[Gedmo\Versioned]
    private array $improvementAreas = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'Le retour tuteur ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Gedmo\Versioned]
    private ?string $mentorFeedback = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'Le retour alternant ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Gedmo\Versioned]
    private ?string $studentFeedback = null;

    #[ORM\Column(type: Types::JSON)]
    #[Assert\Type(type: 'array', message: 'Les recommandations doivent être un tableau.')]
    #[Gedmo\Versioned]
    private array $recommendations = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 5000,
        maxMessage: 'Le rapport de visite ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Gedmo\Versioned]
    private ?string $visitReport = null;

    #[ORM\Column]
    #[Gedmo\Versioned]
    private ?bool $followUpRequired = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Assert\GreaterThan(
        propertyPath: 'visitDate',
        message: 'La prochaine visite doit être postérieure à la visite actuelle.'
    )]
    #[Gedmo\Versioned]
    private ?\DateTimeInterface $nextVisitDate = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(
        min: 1,
        max: 10,
        notInRangeMessage: 'L\'évaluation globale doit être comprise entre {{ min }} et {{ max }}.'
    )]
    #[Gedmo\Versioned]
    private ?int $overallRating = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(
        min: 1,
        max: 10,
        notInRangeMessage: 'L\'évaluation des conditions de travail doit être comprise entre {{ min }} et {{ max }}.'
    )]
    #[Gedmo\Versioned]
    private ?int $workingConditionsRating = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(
        min: 1,
        max: 10,
        notInRangeMessage: 'L\'évaluation de l\'encadrement doit être comprise entre {{ min }} et {{ max }}.'
    )]
    #[Gedmo\Versioned]
    private ?int $supervisionRating = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(
        min: 1,
        max: 10,
        notInRangeMessage: 'L\'évaluation de l\'intégration doit être comprise entre {{ min }} et {{ max }}.'
    )]
    #[Gedmo\Versioned]
    private ?int $integrationRating = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'Les notes ne peuvent pas dépasser {{ limit }} caractères.'
    )]
    private ?string $notes = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive(message: 'La durée doit être positive.')]
    #[Assert\LessThan(480, message: 'La durée ne peut pas dépasser 8 heures (480 minutes).')]
    private ?int $duration = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $createdBy = null;

    // Visit type constants
    public const TYPE_FOLLOW_UP = 'follow_up';
    public const TYPE_EVALUATION = 'evaluation';
    public const TYPE_PROBLEM_SOLVING = 'problem_solving';
    public const TYPE_INTEGRATION = 'integration';
    public const TYPE_FINAL_ASSESSMENT = 'final_assessment';

    // Visit type labels
    public const TYPE_LABELS = [
        self::TYPE_FOLLOW_UP => 'Visite de suivi',
        self::TYPE_EVALUATION => 'Visite d\'évaluation',
        self::TYPE_PROBLEM_SOLVING => 'Résolution de problème',
        self::TYPE_INTEGRATION => 'Visite d\'intégration',
        self::TYPE_FINAL_ASSESSMENT => 'Bilan final'
    ];

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->objectivesChecked = [];
        $this->observedActivities = [];
        $this->strengths = [];
        $this->improvementAreas = [];
        $this->recommendations = [];
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

    public function getVisitor(): ?Teacher
    {
        return $this->visitor;
    }

    public function setVisitor(?Teacher $visitor): static
    {
        $this->visitor = $visitor;
        return $this;
    }

    public function getMentor(): ?Mentor
    {
        return $this->mentor;
    }

    public function setMentor(?Mentor $mentor): static
    {
        $this->mentor = $mentor;
        return $this;
    }

    public function getVisitDate(): ?\DateTimeInterface
    {
        return $this->visitDate;
    }

    public function setVisitDate(?\DateTimeInterface $visitDate): static
    {
        $this->visitDate = $visitDate;
        return $this;
    }

    public function getVisitType(): ?string
    {
        return $this->visitType;
    }

    public function setVisitType(string $visitType): static
    {
        $this->visitType = $visitType;
        return $this;
    }

    public function getObjectivesChecked(): array
    {
        return $this->objectivesChecked;
    }

    public function setObjectivesChecked(array $objectivesChecked): static
    {
        $this->objectivesChecked = $objectivesChecked;
        return $this;
    }

    public function getObservedActivities(): array
    {
        return $this->observedActivities;
    }

    public function setObservedActivities(array $observedActivities): static
    {
        $this->observedActivities = $observedActivities;
        return $this;
    }

    public function getStrengths(): array
    {
        return $this->strengths;
    }

    public function setStrengths(array $strengths): static
    {
        $this->strengths = $strengths;
        return $this;
    }

    public function getImprovementAreas(): array
    {
        return $this->improvementAreas;
    }

    public function setImprovementAreas(array $improvementAreas): static
    {
        $this->improvementAreas = $improvementAreas;
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

    public function getRecommendations(): array
    {
        return $this->recommendations;
    }

    public function setRecommendations(array $recommendations): static
    {
        $this->recommendations = $recommendations;
        return $this;
    }

    public function getVisitReport(): ?string
    {
        return $this->visitReport;
    }

    public function setVisitReport(?string $visitReport): static
    {
        $this->visitReport = $visitReport;
        return $this;
    }

    public function isFollowUpRequired(): ?bool
    {
        return $this->followUpRequired;
    }

    public function setFollowUpRequired(bool $followUpRequired): static
    {
        $this->followUpRequired = $followUpRequired;
        return $this;
    }

    public function getNextVisitDate(): ?\DateTimeInterface
    {
        return $this->nextVisitDate;
    }

    public function setNextVisitDate(?\DateTimeInterface $nextVisitDate): static
    {
        $this->nextVisitDate = $nextVisitDate;
        return $this;
    }

    public function getOverallRating(): ?int
    {
        return $this->overallRating;
    }

    public function setOverallRating(?int $overallRating): static
    {
        $this->overallRating = $overallRating;
        return $this;
    }

    public function getWorkingConditionsRating(): ?int
    {
        return $this->workingConditionsRating;
    }

    public function setWorkingConditionsRating(?int $workingConditionsRating): static
    {
        $this->workingConditionsRating = $workingConditionsRating;
        return $this;
    }

    public function getSupervisionRating(): ?int
    {
        return $this->supervisionRating;
    }

    public function setSupervisionRating(?int $supervisionRating): static
    {
        $this->supervisionRating = $supervisionRating;
        return $this;
    }

    public function getIntegrationRating(): ?int
    {
        return $this->integrationRating;
    }

    public function setIntegrationRating(?int $integrationRating): static
    {
        $this->integrationRating = $integrationRating;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): static
    {
        $this->duration = $duration;
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

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?string $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    /**
     * Get visit type label for display
     */
    public function getVisitTypeLabel(): string
    {
        return self::TYPE_LABELS[$this->visitType] ?? $this->visitType;
    }

    /**
     * Add objective checked
     */
    public function addObjectiveChecked(array $objective): static
    {
        $this->objectivesChecked[] = $objective;
        return $this;
    }

    /**
     * Add observed activity
     */
    public function addObservedActivity(string $activity): static
    {
        $this->observedActivities[] = $activity;
        return $this;
    }

    /**
     * Add strength
     */
    public function addStrength(string $strength): static
    {
        $this->strengths[] = $strength;
        return $this;
    }

    /**
     * Add improvement area
     */
    public function addImprovementArea(string $area): static
    {
        $this->improvementAreas[] = $area;
        return $this;
    }

    /**
     * Add recommendation
     */
    public function addRecommendation(array $recommendation): static
    {
        $this->recommendations[] = $recommendation;
        return $this;
    }

    /**
     * Get average rating
     */
    public function getAverageRating(): ?float
    {
        $ratings = array_filter([
            $this->overallRating,
            $this->workingConditionsRating,
            $this->supervisionRating,
            $this->integrationRating
        ]);

        if (empty($ratings)) {
            return null;
        }

        return array_sum($ratings) / count($ratings);
    }

    /**
     * Get visit duration in human readable format
     */
    public function getDurationFormatted(): string
    {
        if (!$this->duration) {
            return 'Non renseigné';
        }

        $hours = intdiv($this->duration, 60);
        $minutes = $this->duration % 60;

        if ($hours > 0 && $minutes > 0) {
            return $hours . 'h ' . $minutes . 'min';
        } elseif ($hours > 0) {
            return $hours . 'h';
        } else {
            return $minutes . 'min';
        }
    }

    /**
     * Get visit summary for notifications
     */
    public function getVisitSummary(): string
    {
        return sprintf(
            '%s chez %s pour %s le %s',
            $this->getVisitTypeLabel(),
            $this->mentor?->getCompanyName() ?? 'Entreprise',
            $this->student?->getFullName() ?? 'Alternant',
            $this->visitDate?->format('d/m/Y') ?? 'Date non définie'
        );
    }

    /**
     * Check if visit has positive outcome
     */
    public function hasPositiveOutcome(): bool
    {
        $averageRating = $this->getAverageRating();
        return $averageRating !== null && $averageRating >= 7;
    }

    /**
     * Check if visit needs attention
     */
    public function needsAttention(): bool
    {
        $averageRating = $this->getAverageRating();
        return $this->followUpRequired || 
               ($averageRating !== null && $averageRating < 6) ||
               count($this->improvementAreas) > 2;
    }

    /**
     * Get rating display with stars
     */
    public function getRatingDisplay(int $rating): string
    {
        return str_repeat('★', $rating) . str_repeat('☆', 10 - $rating);
    }

    /**
     * Get badge class based on average rating
     */
    public function getRatingBadgeClass(): string
    {
        $averageRating = $this->getAverageRating();
        
        if ($averageRating === null) {
            return 'bg-secondary';
        }

        if ($averageRating >= 8) {
            return 'bg-success';
        } elseif ($averageRating >= 6) {
            return 'bg-warning';
        } else {
            return 'bg-danger';
        }
    }

    /**
     * Generate comprehensive visit assessment
     */
    public function getVisitAssessment(): array
    {
        return [
            'overall_rating' => $this->overallRating,
            'average_rating' => $this->getAverageRating(),
            'positive_outcome' => $this->hasPositiveOutcome(),
            'needs_attention' => $this->needsAttention(),
            'follow_up_required' => $this->followUpRequired,
            'next_visit_scheduled' => $this->nextVisitDate !== null,
            'strengths_count' => count($this->strengths),
            'improvement_areas_count' => count($this->improvementAreas),
            'recommendations_count' => count($this->recommendations)
        ];
    }

    /**
     * Lifecycle callback to update the updatedAt timestamp
     */
    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->getVisitSummary();
    }
}
