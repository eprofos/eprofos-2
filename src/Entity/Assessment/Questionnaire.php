<?php

namespace App\Entity\Assessment;

use App\Entity\Training\Formation;
use App\Repository\QuestionnaireRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Questionnaire entity for Qualiopi criteria 2.8
 * 
 * Represents a positioning and evaluation questionnaire that can be sent to users
 * to assess their knowledge and skills before training.
 */
#[ORM\Entity(repositoryClass: QuestionnaireRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Questionnaire
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    public const TYPES = [
        'positioning' => 'Positionnement',
        'evaluation' => 'Évaluation des acquis',
        'satisfaction' => 'Satisfaction',
        'skills_assessment' => 'Évaluation des compétences'
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $title = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le type est obligatoire.')]
    #[Assert\Choice(
        choices: ['positioning', 'evaluation', 'satisfaction', 'skills_assessment'],
        message: 'Type de questionnaire invalide.'
    )]
    private ?string $type = 'positioning';

    #[ORM\Column(length: 20)]
    #[Assert\Choice(
        choices: ['draft', 'active', 'archived'],
        message: 'Statut invalide.'
    )]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column]
    private ?bool $isMultiStep = true;

    #[ORM\Column]
    private ?int $questionsPerStep = 5;

    #[ORM\Column]
    private ?bool $allowBackNavigation = true;

    #[ORM\Column]
    private ?bool $showProgressBar = true;

    #[ORM\Column]
    private ?bool $requireAllQuestions = true;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero(message: 'La durée limite doit être positive ou nulle.')]
    private ?int $timeLimitMinutes = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $welcomeMessage = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $completionMessage = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $emailSubject = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $emailTemplate = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: Formation::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Formation $formation = null;

    /**
     * @var Collection<int, Question>
     */
    #[ORM\OneToMany(targetEntity: Question::class, mappedBy: 'questionnaire', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['orderIndex' => 'ASC'])]
    private Collection $questions;

    /**
     * @var Collection<int, QuestionnaireResponse>
     */
    #[ORM\OneToMany(targetEntity: QuestionnaireResponse::class, mappedBy: 'questionnaire', cascade: ['remove'])]
    private Collection $responses;

    public function __construct()
    {
        $this->questions = new ArrayCollection();
        $this->responses = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
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

    public function isMultiStep(): ?bool
    {
        return $this->isMultiStep;
    }

    public function setIsMultiStep(bool $isMultiStep): static
    {
        $this->isMultiStep = $isMultiStep;
        return $this;
    }

    public function getQuestionsPerStep(): ?int
    {
        return $this->questionsPerStep;
    }

    public function setQuestionsPerStep(int $questionsPerStep): static
    {
        $this->questionsPerStep = $questionsPerStep;
        return $this;
    }

    public function isAllowBackNavigation(): ?bool
    {
        return $this->allowBackNavigation;
    }

    public function setAllowBackNavigation(bool $allowBackNavigation): static
    {
        $this->allowBackNavigation = $allowBackNavigation;
        return $this;
    }

    public function isShowProgressBar(): ?bool
    {
        return $this->showProgressBar;
    }

    public function setShowProgressBar(bool $showProgressBar): static
    {
        $this->showProgressBar = $showProgressBar;
        return $this;
    }

    public function isRequireAllQuestions(): ?bool
    {
        return $this->requireAllQuestions;
    }

    public function setRequireAllQuestions(bool $requireAllQuestions): static
    {
        $this->requireAllQuestions = $requireAllQuestions;
        return $this;
    }

    public function getTimeLimitMinutes(): ?int
    {
        return $this->timeLimitMinutes;
    }

    public function setTimeLimitMinutes(?int $timeLimitMinutes): static
    {
        $this->timeLimitMinutes = $timeLimitMinutes;
        return $this;
    }

    public function getWelcomeMessage(): ?string
    {
        return $this->welcomeMessage;
    }

    public function setWelcomeMessage(?string $welcomeMessage): static
    {
        $this->welcomeMessage = $welcomeMessage;
        return $this;
    }

    public function getCompletionMessage(): ?string
    {
        return $this->completionMessage;
    }

    public function setCompletionMessage(?string $completionMessage): static
    {
        $this->completionMessage = $completionMessage;
        return $this;
    }

    public function getEmailSubject(): ?string
    {
        return $this->emailSubject;
    }

    public function setEmailSubject(?string $emailSubject): static
    {
        $this->emailSubject = $emailSubject;
        return $this;
    }

    public function getEmailTemplate(): ?string
    {
        return $this->emailTemplate;
    }

    public function setEmailTemplate(?string $emailTemplate): static
    {
        $this->emailTemplate = $emailTemplate;
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
     * @return Collection<int, Question>
     */
    public function getQuestions(): Collection
    {
        return $this->questions;
    }

    public function addQuestion(Question $question): static
    {
        if (!$this->questions->contains($question)) {
            $this->questions->add($question);
            $question->setQuestionnaire($this);
        }

        return $this;
    }

    public function removeQuestion(Question $question): static
    {
        if ($this->questions->removeElement($question)) {
            if ($question->getQuestionnaire() === $this) {
                $question->setQuestionnaire(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, QuestionnaireResponse>
     */
    public function getResponses(): Collection
    {
        return $this->responses;
    }

    public function addResponse(QuestionnaireResponse $response): static
    {
        if (!$this->responses->contains($response)) {
            $this->responses->add($response);
            $response->setQuestionnaire($this);
        }

        return $this;
    }

    public function removeResponse(QuestionnaireResponse $response): static
    {
        if ($this->responses->removeElement($response)) {
            if ($response->getQuestionnaire() === $this) {
                $response->setQuestionnaire(null);
            }
        }

        return $this;
    }

    /**
     * Get the type label for display
     */
    public function getTypeLabel(): string
    {
        return self::TYPES[$this->type] ?? 'Inconnu';
    }

    /**
     * Get the status label for display
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Brouillon',
            self::STATUS_ACTIVE => 'Actif',
            self::STATUS_ARCHIVED => 'Archivé',
            default => 'Inconnu'
        };
    }

    /**
     * Get the status badge class for display
     */
    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'bg-warning',
            self::STATUS_ACTIVE => 'bg-success',
            self::STATUS_ARCHIVED => 'bg-secondary',
            default => 'bg-secondary'
        };
    }

    /**
     * Check if questionnaire is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if questionnaire is draft
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Get active questions only
     * 
     * @return Collection<int, Question>
     */
    public function getActiveQuestions(): Collection
    {
        return $this->questions->filter(function (Question $question) {
            return $question->isActive();
        });
    }

    /**
     * Get number of questions
     */
    public function getQuestionCount(): int
    {
        return $this->getActiveQuestions()->count();
    }

    /**
     * Get number of steps for multi-step questionnaire
     */
    public function getStepCount(): int
    {
        if (!$this->isMultiStep) {
            return 1;
        }
        
        $questionCount = $this->getQuestionCount();
        return $questionCount > 0 ? ceil($questionCount / $this->questionsPerStep) : 1;
    }

    /**
     * Get questions for a specific step
     * 
     * @return Collection<int, Question>
     */
    public function getQuestionsForStep(int $step): Collection
    {
        if (!$this->isMultiStep) {
            return $this->getActiveQuestions();
        }

        $activeQuestions = $this->getActiveQuestions()->toArray();
        $startIndex = ($step - 1) * $this->questionsPerStep;
        $endIndex = $startIndex + $this->questionsPerStep;
        
        $stepQuestions = array_slice($activeQuestions, $startIndex, $this->questionsPerStep, true);
        
        return new ArrayCollection($stepQuestions);
    }

    /**
     * Get response count
     */
    public function getResponseCount(): int
    {
        return $this->responses->count();
    }

    /**
     * Get completed responses count
     */
    public function getCompletedResponseCount(): int
    {
        return $this->responses->filter(function (QuestionnaireResponse $response) {
            return $response->isCompleted();
        })->count();
    }

    /**
     * Get completion rate as percentage
     */
    public function getCompletionRate(): float
    {
        $totalResponses = $this->getResponseCount();
        if ($totalResponses === 0) {
            return 0;
        }
        
        return ($this->getCompletedResponseCount() / $totalResponses) * 100;
    }

    /**
     * Generate automatic slug from title
     */
    public function generateSlug(SluggerInterface $slugger): static
    {
        if ($this->title && !$this->slug) {
            $this->slug = $slugger->slug($this->title)->lower();
        }
        
        return $this;
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
        return $this->title ?? '';
    }
}
