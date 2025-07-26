<?php

namespace App\Entity\Alternance;

use App\Entity\User\Student;
use App\Entity\User\Teacher;
use App\Entity\User\Mentor;
use App\Repository\Alternance\SkillsAssessmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Gedmo\Mapping\Annotation as Gedmo;

/**
 * SkillsAssessment entity for evaluating student competencies in alternance programs
 * 
 * This entity implements cross-evaluation between training center and company,
 * essential for Qualiopi compliance and demonstrating progressive skills acquisition.
 */
#[ORM\Entity(repositoryClass: SkillsAssessmentRepository::class)]
#[ORM\Table(name: 'skills_assessments')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['student_id', 'assessment_date'], name: 'idx_student_assessment_date')]
#[ORM\Index(columns: ['context'], name: 'idx_context')]
#[ORM\Index(columns: ['assessment_type'], name: 'idx_assessment_type')]
#[Gedmo\Loggable]
class SkillsAssessment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Student::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'L\'alternant évalué est obligatoire.')]
    private ?Student $student = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le type d\'évaluation est obligatoire.')]
    #[Assert\Choice(
        choices: self::ASSESSMENT_TYPES,
        message: 'Type d\'évaluation invalide.'
    )]
    #[Gedmo\Versioned]
    private ?string $assessmentType = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le contexte d\'évaluation est obligatoire.')]
    #[Assert\Choice(
        choices: self::CONTEXTS,
        message: 'Contexte d\'évaluation invalide.'
    )]
    #[Gedmo\Versioned]
    private ?string $context = null;

    #[ORM\ManyToOne(targetEntity: Teacher::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Teacher $centerEvaluator = null;

    #[ORM\ManyToOne(targetEntity: Mentor::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Mentor $mentorEvaluator = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'La date d\'évaluation est obligatoire.')]
    #[Gedmo\Versioned]
    private ?\DateTimeInterface $assessmentDate = null;

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'Les compétences évaluées sont obligatoires.')]
    #[Gedmo\Versioned]
    private array $skillsEvaluated = [];

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'Les notes centre formation sont obligatoires.')]
    #[Gedmo\Versioned]
    private array $centerScores = [];

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'Les notes entreprise sont obligatoires.')]
    #[Gedmo\Versioned]
    private array $companyScores = [];

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'Les compétences globales sont obligatoires.')]
    #[Gedmo\Versioned]
    private array $globalCompetencies = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $centerComments = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $mentorComments = null;

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotNull(message: 'Le plan de développement est obligatoire.')]
    #[Gedmo\Versioned]
    private array $developmentPlan = [];

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'L\'évaluation globale est obligatoire.')]
    #[Assert\Choice(
        choices: self::OVERALL_RATINGS,
        message: 'Évaluation globale invalide.'
    )]
    #[Gedmo\Versioned]
    private ?string $overallRating = null;

    #[ORM\ManyToOne(targetEntity: MissionAssignment::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?MissionAssignment $relatedMission = null;

    #[ORM\Column]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * Available assessment types
     */
    public const ASSESSMENT_TYPES = [
        'formative' => 'Formative',
        'sommative' => 'Sommative',
        'certification' => 'Certification',
        'intermediate' => 'Intermédiaire',
        'final' => 'Finale'
    ];

    /**
     * Available assessment contexts
     */
    public const CONTEXTS = [
        'centre' => 'Centre de formation',
        'entreprise' => 'Entreprise',
        'mixte' => 'Mixte (centre + entreprise)'
    ];

    /**
     * Available overall ratings
     */
    public const OVERALL_RATINGS = [
        'excellent' => 'Excellent',
        'satisfaisant' => 'Satisfaisant',
        'moyen' => 'Moyen',
        'insuffisant' => 'Insuffisant',
        'non_evalue' => 'Non évalué'
    ];

    /**
     * Standard skills framework
     */
    public const STANDARD_SKILLS = [
        'technical' => [
            'name' => 'Compétences techniques',
            'subcategories' => [
                'programming' => 'Programmation',
                'database' => 'Bases de données',
                'networks' => 'Réseaux',
                'security' => 'Sécurité',
                'tools' => 'Outils et technologies'
            ]
        ],
        'transversal' => [
            'name' => 'Compétences transversales',
            'subcategories' => [
                'communication' => 'Communication',
                'teamwork' => 'Travail en équipe',
                'autonomy' => 'Autonomie',
                'problem_solving' => 'Résolution de problèmes',
                'time_management' => 'Gestion du temps'
            ]
        ],
        'professional' => [
            'name' => 'Compétences professionnelles',
            'subcategories' => [
                'project_management' => 'Gestion de projet',
                'client_relation' => 'Relation client',
                'quality' => 'Qualité',
                'innovation' => 'Innovation',
                'leadership' => 'Leadership'
            ]
        ]
    ];

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->assessmentDate = new \DateTime();
        $this->skillsEvaluated = [];
        $this->centerScores = [];
        $this->companyScores = [];
        $this->globalCompetencies = [];
        $this->developmentPlan = [];
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

    public function getAssessmentType(): ?string
    {
        return $this->assessmentType;
    }

    public function setAssessmentType(string $assessmentType): static
    {
        $this->assessmentType = $assessmentType;
        return $this;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(string $context): static
    {
        $this->context = $context;
        return $this;
    }

    public function getCenterEvaluator(): ?Teacher
    {
        return $this->centerEvaluator;
    }

    public function setCenterEvaluator(?Teacher $centerEvaluator): static
    {
        $this->centerEvaluator = $centerEvaluator;
        return $this;
    }

    public function getMentorEvaluator(): ?Mentor
    {
        return $this->mentorEvaluator;
    }

    public function setMentorEvaluator(?Mentor $mentorEvaluator): static
    {
        $this->mentorEvaluator = $mentorEvaluator;
        return $this;
    }

    public function getAssessmentDate(): ?\DateTimeInterface
    {
        return $this->assessmentDate;
    }

    public function setAssessmentDate(\DateTimeInterface $assessmentDate): static
    {
        $this->assessmentDate = $assessmentDate;
        return $this;
    }

    public function getSkillsEvaluated(): array
    {
        return $this->skillsEvaluated;
    }

    public function setSkillsEvaluated(array $skillsEvaluated): static
    {
        $this->skillsEvaluated = $skillsEvaluated;
        return $this;
    }

    public function getCenterScores(): array
    {
        return $this->centerScores;
    }

    public function setCenterScores(array $centerScores): static
    {
        $this->centerScores = $centerScores;
        return $this;
    }

    public function getCompanyScores(): array
    {
        return $this->companyScores;
    }

    public function setCompanyScores(array $companyScores): static
    {
        $this->companyScores = $companyScores;
        return $this;
    }

    public function getGlobalCompetencies(): array
    {
        return $this->globalCompetencies;
    }

    public function setGlobalCompetencies(array $globalCompetencies): static
    {
        $this->globalCompetencies = $globalCompetencies;
        return $this;
    }

    public function getCenterComments(): ?string
    {
        return $this->centerComments;
    }

    public function setCenterComments(?string $centerComments): static
    {
        $this->centerComments = $centerComments;
        return $this;
    }

    public function getMentorComments(): ?string
    {
        return $this->mentorComments;
    }

    public function setMentorComments(?string $mentorComments): static
    {
        $this->mentorComments = $mentorComments;
        return $this;
    }

    public function getDevelopmentPlan(): array
    {
        return $this->developmentPlan;
    }

    public function setDevelopmentPlan(array $developmentPlan): static
    {
        $this->developmentPlan = $developmentPlan;
        return $this;
    }

    public function getOverallRating(): ?string
    {
        return $this->overallRating;
    }

    public function setOverallRating(string $overallRating): static
    {
        $this->overallRating = $overallRating;
        return $this;
    }

    public function getRelatedMission(): ?MissionAssignment
    {
        return $this->relatedMission;
    }

    public function setRelatedMission(?MissionAssignment $relatedMission): static
    {
        $this->relatedMission = $relatedMission;
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
     * Get assessment type label
     */
    public function getAssessmentTypeLabel(): string
    {
        return self::ASSESSMENT_TYPES[$this->assessmentType] ?? $this->assessmentType;
    }

    /**
     * Get context label
     */
    public function getContextLabel(): string
    {
        return self::CONTEXTS[$this->context] ?? $this->context;
    }

    /**
     * Get overall rating label
     */
    public function getOverallRatingLabel(): string
    {
        return self::OVERALL_RATINGS[$this->overallRating] ?? $this->overallRating;
    }

    /**
     * Get overall rating badge class
     */
    public function getOverallRatingBadgeClass(): string
    {
        return match ($this->overallRating) {
            'excellent' => 'badge-success',
            'satisfaisant' => 'badge-primary',
            'moyen' => 'badge-warning',
            'insuffisant' => 'badge-danger',
            'non_evalue' => 'badge-secondary',
            default => 'badge-secondary'
        };
    }

    /**
     * Calculate average center score
     */
    public function getAverageCenterScore(): float
    {
        if (empty($this->centerScores)) {
            return 0.0;
        }

        $total = 0;
        $count = 0;

        foreach ($this->centerScores as $score) {
            if (isset($score['value']) && is_numeric($score['value'])) {
                $total += (float) $score['value'];
                $count++;
            }
        }

        return $count > 0 ? $total / $count : 0.0;
    }

    /**
     * Calculate average company score
     */
    public function getAverageCompanyScore(): float
    {
        if (empty($this->companyScores)) {
            return 0.0;
        }

        $total = 0;
        $count = 0;

        foreach ($this->companyScores as $score) {
            if (isset($score['value']) && is_numeric($score['value'])) {
                $total += (float) $score['value'];
                $count++;
            }
        }

        return $count > 0 ? $total / $count : 0.0;
    }

    /**
     * Calculate overall average score
     */
    public function getOverallAverageScore(): float
    {
        $centerAvg = $this->getAverageCenterScore();
        $companyAvg = $this->getAverageCompanyScore();

        if ($centerAvg > 0 && $companyAvg > 0) {
            return ($centerAvg + $companyAvg) / 2;
        } elseif ($centerAvg > 0) {
            return $centerAvg;
        } elseif ($companyAvg > 0) {
            return $companyAvg;
        }

        return 0.0;
    }

    /**
     * Check if assessment has cross-evaluation (both center and company scores)
     */
    public function hasCrossEvaluation(): bool
    {
        return !empty($this->centerScores) && !empty($this->companyScores);
    }

    /**
     * Check if assessment is complete
     */
    public function isComplete(): bool
    {
        $hasScores = false;
        
        if ($this->context === 'centre') {
            $hasScores = !empty($this->centerScores);
        } elseif ($this->context === 'entreprise') {
            $hasScores = !empty($this->companyScores);
        } else { // mixte
            $hasScores = !empty($this->centerScores) && !empty($this->companyScores);
        }

        return $hasScores && !empty($this->skillsEvaluated) && $this->overallRating !== null;
    }

    /**
     * Get competency gap analysis
     */
    public function getCompetencyGaps(): array
    {
        $gaps = [];
        
        if (!$this->hasCrossEvaluation()) {
            return $gaps;
        }

        foreach ($this->centerScores as $skill => $centerScore) {
            if (isset($this->companyScores[$skill])) {
                $centerValue = (float) ($centerScore['value'] ?? 0);
                $companyValue = (float) ($this->companyScores[$skill]['value'] ?? 0);
                $gap = abs($centerValue - $companyValue);
                
                if ($gap > 2.0) { // Significant gap threshold
                    $gaps[$skill] = [
                        'center_score' => $centerValue,
                        'company_score' => $companyValue,
                        'gap' => $gap,
                        'needs_attention' => true
                    ];
                }
            }
        }

        return $gaps;
    }

    /**
     * Add skill evaluation
     */
    public function addSkillEvaluation(string $skillCode, string $skillName, ?float $centerScore = null, ?float $companyScore = null): static
    {
        // Add to skills evaluated
        $this->skillsEvaluated[$skillCode] = [
            'name' => $skillName,
            'code' => $skillCode,
            'evaluated_at' => (new \DateTime())->format('Y-m-d H:i:s')
        ];

        // Add center score if provided
        if ($centerScore !== null) {
            $this->centerScores[$skillCode] = [
                'value' => $centerScore,
                'max_value' => 20,
                'evaluated_at' => (new \DateTime())->format('Y-m-d H:i:s')
            ];
        }

        // Add company score if provided
        if ($companyScore !== null) {
            $this->companyScores[$skillCode] = [
                'value' => $companyScore,
                'max_value' => 20,
                'evaluated_at' => (new \DateTime())->format('Y-m-d H:i:s')
            ];
        }

        return $this;
    }

    /**
     * Add development plan item
     */
    public function addDevelopmentPlanItem(string $skill, string $objective, string $actions, ?string $deadline = null): static
    {
        $this->developmentPlan[] = [
            'skill' => $skill,
            'objective' => $objective,
            'actions' => $actions,
            'deadline' => $deadline,
            'status' => 'planned',
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s')
        ];

        return $this;
    }

    /**
     * Get development plan summary
     */
    public function getDevelopmentPlanSummary(): array
    {
        $summary = [
            'total_items' => count($this->developmentPlan),
            'planned' => 0,
            'in_progress' => 0,
            'completed' => 0
        ];

        foreach ($this->developmentPlan as $item) {
            $status = $item['status'] ?? 'planned';
            if (isset($summary[$status])) {
                $summary[$status]++;
            }
        }

        return $summary;
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
        return sprintf(
            '%s - %s (%s) - %s',
            $this->student?->getFullName() ?? 'Alternant inconnu',
            $this->getAssessmentTypeLabel(),
            $this->getContextLabel(),
            $this->assessmentDate?->format('d/m/Y') ?? ''
        );
    }
}
