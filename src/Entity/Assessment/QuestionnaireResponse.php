<?php

namespace App\Entity\Assessment;

use App\Entity\Training\Formation;
use App\Repository\QuestionnaireResponseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * QuestionnaireResponse entity representing a user's response to a questionnaire
 * 
 * Contains user information and all their responses to questionnaire questions
 */
#[ORM\Entity(repositoryClass: QuestionnaireResponseRepository::class)]
#[ORM\HasLifecycleCallbacks]
class QuestionnaireResponse
{
    public const STATUS_STARTED = 'started';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ABANDONED = 'abandoned';

    public const EVALUATION_STATUS_PENDING = 'pending';
    public const EVALUATION_STATUS_IN_REVIEW = 'in_review';
    public const EVALUATION_STATUS_COMPLETED = 'completed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $token = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $lastName = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
    #[Assert\Email(message: 'Veuillez saisir une adresse email valide.')]
    private ?string $email = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $company = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(
        choices: ['started', 'in_progress', 'completed', 'abandoned'],
        message: 'Statut invalide.'
    )]
    private string $status = self::STATUS_STARTED;

    #[ORM\Column(nullable: true)]
    private ?int $currentStep = 1;

    #[ORM\Column(nullable: true)]
    private ?int $totalScore = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxPossibleScore = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $scorePercentage = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(
        choices: ['pending', 'in_review', 'completed'],
        message: 'Statut d\'évaluation invalide.'
    )]
    private string $evaluationStatus = self::EVALUATION_STATUS_PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $evaluatorNotes = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $recommendation = null;

    #[ORM\Column(nullable: true)]
    private ?int $durationMinutes = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $evaluatedAt = null;

    #[ORM\ManyToOne(targetEntity: Questionnaire::class, inversedBy: 'responses')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Questionnaire $questionnaire = null;

    #[ORM\ManyToOne(targetEntity: Formation::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Formation $formation = null;

    /**
     * @var Collection<int, QuestionResponse>
     */
    #[ORM\OneToMany(targetEntity: QuestionResponse::class, mappedBy: 'questionnaireResponse', cascade: ['persist', 'remove'])]
    private Collection $questionResponses;

    public function __construct()
    {
        $this->questionResponses = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->token = $this->generateToken();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;
        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(?string $company): static
    {
        $this->company = $company;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getCurrentStep(): ?int
    {
        return $this->currentStep;
    }

    public function setCurrentStep(?int $currentStep): static
    {
        $this->currentStep = $currentStep;
        return $this;
    }

    public function getTotalScore(): ?int
    {
        return $this->totalScore;
    }

    public function setTotalScore(?int $totalScore): static
    {
        $this->totalScore = $totalScore;
        return $this;
    }

    public function getMaxPossibleScore(): ?int
    {
        return $this->maxPossibleScore;
    }

    public function setMaxPossibleScore(?int $maxPossibleScore): static
    {
        $this->maxPossibleScore = $maxPossibleScore;
        return $this;
    }

    public function getScorePercentage(): ?string
    {
        return $this->scorePercentage;
    }

    public function setScorePercentage(?string $scorePercentage): static
    {
        $this->scorePercentage = $scorePercentage;
        return $this;
    }

    public function getEvaluationStatus(): string
    {
        return $this->evaluationStatus;
    }

    public function setEvaluationStatus(string $evaluationStatus): static
    {
        $this->evaluationStatus = $evaluationStatus;
        return $this;
    }

    public function getEvaluatorNotes(): ?string
    {
        return $this->evaluatorNotes;
    }

    public function setEvaluatorNotes(?string $evaluatorNotes): static
    {
        $this->evaluatorNotes = $evaluatorNotes;
        return $this;
    }

    public function getRecommendation(): ?string
    {
        return $this->recommendation;
    }

    public function setRecommendation(?string $recommendation): static
    {
        $this->recommendation = $recommendation;
        return $this;
    }

    public function getDurationMinutes(): ?int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(?int $durationMinutes): static
    {
        $this->durationMinutes = $durationMinutes;
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

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getEvaluatedAt(): ?\DateTimeImmutable
    {
        return $this->evaluatedAt;
    }

    public function setEvaluatedAt(?\DateTimeImmutable $evaluatedAt): static
    {
        $this->evaluatedAt = $evaluatedAt;
        return $this;
    }

    public function getQuestionnaire(): ?Questionnaire
    {
        return $this->questionnaire;
    }

    public function setQuestionnaire(?Questionnaire $questionnaire): static
    {
        $this->questionnaire = $questionnaire;
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

    /**
     * @return Collection<int, QuestionResponse>
     */
    public function getQuestionResponses(): Collection
    {
        return $this->questionResponses;
    }

    public function addQuestionResponse(QuestionResponse $questionResponse): static
    {
        if (!$this->questionResponses->contains($questionResponse)) {
            $this->questionResponses->add($questionResponse);
            $questionResponse->setQuestionnaireResponse($this);
        }

        return $this;
    }

    public function removeQuestionResponse(QuestionResponse $questionResponse): static
    {
        if ($this->questionResponses->removeElement($questionResponse)) {
            if ($questionResponse->getQuestionnaireResponse() === $this) {
                $questionResponse->setQuestionnaireResponse(null);
            }
        }

        return $this;
    }

    /**
     * Get the full name of the respondent
     */
    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    /**
     * Get the status label for display
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_STARTED => 'Démarré',
            self::STATUS_IN_PROGRESS => 'En cours',
            self::STATUS_COMPLETED => 'Terminé',
            self::STATUS_ABANDONED => 'Abandonné',
            default => 'Inconnu'
        };
    }

    /**
     * Get the status badge class for display
     */
    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_STARTED => 'bg-info',
            self::STATUS_IN_PROGRESS => 'bg-warning',
            self::STATUS_COMPLETED => 'bg-success',
            self::STATUS_ABANDONED => 'bg-danger',
            default => 'bg-secondary'
        };
    }

    /**
     * Get the evaluation status label for display
     */
    public function getEvaluationStatusLabel(): string
    {
        return match ($this->evaluationStatus) {
            self::EVALUATION_STATUS_PENDING => 'En attente',
            self::EVALUATION_STATUS_IN_REVIEW => 'En cours d\'évaluation',
            self::EVALUATION_STATUS_COMPLETED => 'Évalué',
            default => 'Inconnu'
        };
    }

    /**
     * Get the evaluation status badge class for display
     */
    public function getEvaluationStatusBadgeClass(): string
    {
        return match ($this->evaluationStatus) {
            self::EVALUATION_STATUS_PENDING => 'bg-warning',
            self::EVALUATION_STATUS_IN_REVIEW => 'bg-info',
            self::EVALUATION_STATUS_COMPLETED => 'bg-success',
            default => 'bg-secondary'
        };
    }

    /**
     * Check if response is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if response is in progress
     */
    public function isInProgress(): bool
    {
        return in_array($this->status, [self::STATUS_STARTED, self::STATUS_IN_PROGRESS]);
    }

    /**
     * Check if evaluation is completed
     */
    public function isEvaluated(): bool
    {
        return $this->evaluationStatus === self::EVALUATION_STATUS_COMPLETED;
    }

    /**
     * Mark response as started
     */
    public function markAsStarted(): static
    {
        $this->status = self::STATUS_STARTED;
        $this->startedAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * Mark response as in progress
     */
    public function markAsInProgress(): static
    {
        $this->status = self::STATUS_IN_PROGRESS;
        if (!$this->startedAt) {
            $this->startedAt = new \DateTimeImmutable();
        }
        return $this;
    }

    /**
     * Mark response as completed
     */
    public function markAsCompleted(): static
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completedAt = new \DateTimeImmutable();
        $this->calculateScore();
        return $this;
    }

    /**
     * Mark as evaluated
     */
    public function markAsEvaluated(): static
    {
        $this->evaluationStatus = self::EVALUATION_STATUS_COMPLETED;
        $this->evaluatedAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * Calculate total score based on question responses
     */
    public function calculateScore(): static
    {
        $totalScore = 0;
        $maxPossibleScore = 0;

        foreach ($this->questionResponses as $response) {
            $questionScore = $response->calculateScore();
            $totalScore += $questionScore;
            
            // Add question points to max possible score
            $maxPossibleScore += $response->getQuestion()->getPoints() ?? 0;
        }

        $this->totalScore = $totalScore;
        $this->maxPossibleScore = $maxPossibleScore;
        
        if ($maxPossibleScore > 0) {
            $this->scorePercentage = number_format(($totalScore / $maxPossibleScore) * 100, 2);
        }

        return $this;
    }

    /**
     * Get progress percentage
     */
    public function getProgressPercentage(): int
    {
        if (!$this->questionnaire) {
            return 0;
        }
        
        $totalSteps = $this->questionnaire->getStepCount();
        if ($totalSteps === 0) {
            return 0;
        }
        
        return (int) (($this->currentStep / $totalSteps) * 100);
    }

    /**
     * Get completion time in minutes
     */
    public function getCompletionTimeMinutes(): ?int
    {
        if (!$this->startedAt || !$this->completedAt) {
            return null;
        }
        
        return $this->startedAt->diff($this->completedAt)->i;
    }

    /**
     * Get response for a specific question
     */
    public function getResponseForQuestion(Question $question): ?QuestionResponse
    {
        foreach ($this->questionResponses as $response) {
            if ($response->getQuestion() === $question) {
                return $response;
            }
        }
        
        return null;
    }

    /**
     * Check if response has answer for a specific question
     */
    public function hasAnswerForQuestion(Question $question): bool
    {
        $response = $this->getResponseForQuestion($question);
        return $response && $response->hasAnswer();
    }

    /**
     * Generate unique token for the response
     */
    private function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Lifecycle callback to update the updatedAt timestamp
     */
    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Get participant name (alias for getFullName)
     */
    public function getParticipantName(): string
    {
        return $this->getFullName();
    }

    /**
     * Get participant email
     */
    public function getParticipantEmail(): string
    {
        return $this->email;
    }

    /**
     * Get final score as percentage
     */
    public function getFinalScore(): ?float
    {
        return $this->scorePercentage ? (float) $this->scorePercentage : null;
    }

    /**
     * Get score obtained
     */
    public function getScoreObtained(): ?int
    {
        return $this->totalScore;
    }

    /**
     * Get score total
     */
    public function getScoreTotal(): ?int
    {
        return $this->maxPossibleScore;
    }

    /**
     * Check if response has file responses
     */
    public function hasFileResponses(): bool
    {
        foreach ($this->questionResponses as $response) {
            if ($response->getFileResponse()) {
                return true;
            }
        }
        return false;
    }

    public function __toString(): string
    {
        return $this->getFullName() . ' - ' . ($this->questionnaire?->getTitle() ?? '');
    }
}
