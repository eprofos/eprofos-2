<?php

namespace App\Entity\Assessment;

use App\Repository\QuestionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Question entity for questionnaire system
 * 
 * Represents individual questions within a questionnaire with various types:
 * text, textarea, single_choice, multiple_choice, file_upload
 */
#[ORM\Entity(repositoryClass: QuestionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Question
{
    public const TYPE_TEXT = 'text';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_SINGLE_CHOICE = 'single_choice';
    public const TYPE_MULTIPLE_CHOICE = 'multiple_choice';
    public const TYPE_FILE_UPLOAD = 'file_upload';
    public const TYPE_NUMBER = 'number';
    public const TYPE_EMAIL = 'email';
    public const TYPE_DATE = 'date';

    public const TYPES = [
        self::TYPE_TEXT => 'Texte court',
        self::TYPE_TEXTAREA => 'Texte long',
        self::TYPE_SINGLE_CHOICE => 'Choix unique (QCM)',
        self::TYPE_MULTIPLE_CHOICE => 'Choix multiple',
        self::TYPE_FILE_UPLOAD => 'Téléchargement de fichier',
        self::TYPE_NUMBER => 'Nombre',
        self::TYPE_EMAIL => 'Email',
        self::TYPE_DATE => 'Date'
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La question est obligatoire.')]
    #[Assert\Length(
        min: 10,
        max: 1000,
        minMessage: 'La question doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'La question ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $questionText = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le type de question est obligatoire.')]
    #[Assert\Choice(
        choices: ['text', 'textarea', 'single_choice', 'multiple_choice', 'file_upload', 'number', 'email', 'date'],
        message: 'Type de question invalide.'
    )]
    private ?string $type = self::TYPE_TEXT;

    #[ORM\Column]
    #[Assert\PositiveOrZero(message: 'L\'ordre doit être positif ou nul.')]
    private ?int $orderIndex = 0;

    #[ORM\Column]
    private ?bool $isRequired = true;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $helpText = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $placeholder = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero(message: 'La longueur minimale doit être positive ou nulle.')]
    private ?int $minLength = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive(message: 'La longueur maximale doit être positive.')]
    private ?int $maxLength = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $validationRules = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $allowedFileTypes = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive(message: 'La taille maximale de fichier doit être positive.')]
    private ?int $maxFileSize = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero(message: 'Les points doivent être positifs ou nuls.')]
    private ?int $points = 0;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: Questionnaire::class, inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Questionnaire $questionnaire = null;

    /**
     * @var Collection<int, QuestionOption>
     */
    #[ORM\OneToMany(targetEntity: QuestionOption::class, mappedBy: 'question', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['orderIndex' => 'ASC'])]
    private Collection $options;

    /**
     * @var Collection<int, QuestionResponse>
     */
    #[ORM\OneToMany(targetEntity: QuestionResponse::class, mappedBy: 'question', cascade: ['remove'])]
    private Collection $responses;

    public function __construct()
    {
        $this->options = new ArrayCollection();
        $this->responses = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuestionText(): ?string
    {
        return $this->questionText;
    }

    public function setQuestionText(string $questionText): static
    {
        $this->questionText = $questionText;
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

    public function getOrderIndex(): ?int
    {
        return $this->orderIndex;
    }

    public function setOrderIndex(int $orderIndex): static
    {
        $this->orderIndex = $orderIndex;
        return $this;
    }

    public function isRequired(): ?bool
    {
        return $this->isRequired;
    }

    public function setIsRequired(bool $isRequired): static
    {
        $this->isRequired = $isRequired;
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getHelpText(): ?string
    {
        return $this->helpText;
    }

    public function setHelpText(?string $helpText): static
    {
        $this->helpText = $helpText;
        return $this;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    public function setPlaceholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;
        return $this;
    }

    public function getMinLength(): ?int
    {
        return $this->minLength;
    }

    public function setMinLength(?int $minLength): static
    {
        $this->minLength = $minLength;
        return $this;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    public function setMaxLength(?int $maxLength): static
    {
        $this->maxLength = $maxLength;
        return $this;
    }

    public function getValidationRules(): ?array
    {
        return $this->validationRules;
    }

    public function setValidationRules(?array $validationRules): static
    {
        $this->validationRules = $validationRules;
        return $this;
    }

    public function getAllowedFileTypes(): ?array
    {
        return $this->allowedFileTypes;
    }

    public function setAllowedFileTypes(?array $allowedFileTypes): static
    {
        $this->allowedFileTypes = $allowedFileTypes;
        return $this;
    }

    public function getMaxFileSize(): ?int
    {
        return $this->maxFileSize;
    }

    public function setMaxFileSize(?int $maxFileSize): static
    {
        $this->maxFileSize = $maxFileSize;
        return $this;
    }

    public function getPoints(): ?int
    {
        return $this->points;
    }

    public function setPoints(?int $points): static
    {
        $this->points = $points;
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

    public function getQuestionnaire(): ?Questionnaire
    {
        return $this->questionnaire;
    }

    public function setQuestionnaire(?Questionnaire $questionnaire): static
    {
        $this->questionnaire = $questionnaire;
        return $this;
    }

    /**
     * @return Collection<int, QuestionOption>
     */
    public function getOptions(): Collection
    {
        return $this->options;
    }

    public function addOption(QuestionOption $option): static
    {
        if (!$this->options->contains($option)) {
            $this->options->add($option);
            $option->setQuestion($this);
        }

        return $this;
    }

    public function removeOption(QuestionOption $option): static
    {
        if ($this->options->removeElement($option)) {
            if ($option->getQuestion() === $this) {
                $option->setQuestion(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, QuestionResponse>
     */
    public function getResponses(): Collection
    {
        return $this->responses;
    }

    public function addResponse(QuestionResponse $response): static
    {
        if (!$this->responses->contains($response)) {
            $this->responses->add($response);
            $response->setQuestion($this);
        }

        return $this;
    }

    public function removeResponse(QuestionResponse $response): static
    {
        if ($this->responses->removeElement($response)) {
            if ($response->getQuestion() === $this) {
                $response->setQuestion(null);
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
     * Check if question has choices (single or multiple choice)
     */
    public function hasChoices(): bool
    {
        return in_array($this->type, [self::TYPE_SINGLE_CHOICE, self::TYPE_MULTIPLE_CHOICE]);
    }

    /**
     * Check if question is a file upload
     */
    public function isFileUpload(): bool
    {
        return $this->type === self::TYPE_FILE_UPLOAD;
    }

    /**
     * Check if question allows text input
     */
    public function isTextInput(): bool
    {
        return in_array($this->type, [self::TYPE_TEXT, self::TYPE_TEXTAREA, self::TYPE_EMAIL]);
    }

    /**
     * Get active options only
     * 
     * @return Collection<int, QuestionOption>
     */
    public function getActiveOptions(): Collection
    {
        return $this->options->filter(function (QuestionOption $option) {
            return $option->isActive();
        });
    }

    /**
     * Get correct options (for QCM evaluation)
     * 
     * @return Collection<int, QuestionOption>
     */
    public function getCorrectOptions(): Collection
    {
        return $this->options->filter(function (QuestionOption $option) {
            return $option->isCorrect();
        });
    }

    /**
     * Check if question has correct answers defined
     */
    public function hasCorrectAnswers(): bool
    {
        return $this->getCorrectOptions()->count() > 0;
    }

    /**
     * Get response count for this question
     */
    public function getResponseCount(): int
    {
        return $this->responses->count();
    }

    /**
     * Get formatted file size limit
     */
    public function getFormattedMaxFileSize(): string
    {
        if (!$this->maxFileSize) {
            return '';
        }
        
        if ($this->maxFileSize < 1024) {
            return $this->maxFileSize . ' B';
        } elseif ($this->maxFileSize < 1024 * 1024) {
            return round($this->maxFileSize / 1024, 1) . ' KB';
        } else {
            return round($this->maxFileSize / (1024 * 1024), 1) . ' MB';
        }
    }

    /**
     * Get allowed file types as string
     */
    public function getAllowedFileTypesString(): string
    {
        if (!$this->allowedFileTypes) {
            return '';
        }
        
        return implode(', ', $this->allowedFileTypes);
    }

    /**
     * Get step number (for multi-step questionnaires)
     */
    public function getStepNumber(): int
    {
        // This could be calculated based on order and questions per step
        // For now, return 1 as default
        return 1;
    }

    /**
     * Get max points
     */
    public function getMaxPoints(): ?int
    {
        return $this->points;
    }

    /**
     * Get sort order (alias for orderIndex)
     */
    public function getSortOrder(): ?int
    {
        return $this->orderIndex;
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
        return $this->questionText ?? '';
    }
}
