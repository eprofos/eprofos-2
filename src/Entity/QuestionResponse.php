<?php

namespace App\Entity;

use App\Repository\QuestionResponseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * QuestionResponse entity representing a user's response to a specific question
 * 
 * Stores the actual answer given by the user for a question
 */
#[ORM\Entity(repositoryClass: QuestionResponseRepository::class)]
#[ORM\HasLifecycleCallbacks]
class QuestionResponse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $textResponse = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $choiceResponse = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fileResponse = null;

    #[ORM\Column(nullable: true)]
    private ?int $numberResponse = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateResponse = null;

    #[ORM\Column(nullable: true)]
    private ?int $scoreEarned = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: Question::class, inversedBy: 'responses')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Question $question = null;

    #[ORM\ManyToOne(targetEntity: QuestionnaireResponse::class, inversedBy: 'questionResponses')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?QuestionnaireResponse $questionnaireResponse = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTextResponse(): ?string
    {
        return $this->textResponse;
    }

    public function setTextResponse(?string $textResponse): static
    {
        $this->textResponse = $textResponse;
        return $this;
    }

    public function getChoiceResponse(): ?array
    {
        return $this->choiceResponse;
    }

    public function setChoiceResponse(?array $choiceResponse): static
    {
        $this->choiceResponse = $choiceResponse;
        return $this;
    }

    public function getFileResponse(): ?string
    {
        return $this->fileResponse;
    }

    public function setFileResponse(?string $fileResponse): static
    {
        $this->fileResponse = $fileResponse;
        return $this;
    }

    public function getNumberResponse(): ?int
    {
        return $this->numberResponse;
    }

    public function setNumberResponse(?int $numberResponse): static
    {
        $this->numberResponse = $numberResponse;
        return $this;
    }

    public function getDateResponse(): ?\DateTimeInterface
    {
        return $this->dateResponse;
    }

    public function setDateResponse(?\DateTimeInterface $dateResponse): static
    {
        $this->dateResponse = $dateResponse;
        return $this;
    }

    public function getScoreEarned(): ?int
    {
        return $this->scoreEarned;
    }

    public function setScoreEarned(?int $scoreEarned): static
    {
        $this->scoreEarned = $scoreEarned;
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

    public function getQuestion(): ?Question
    {
        return $this->question;
    }

    public function setQuestion(?Question $question): static
    {
        $this->question = $question;
        return $this;
    }

    public function getQuestionnaireResponse(): ?QuestionnaireResponse
    {
        return $this->questionnaireResponse;
    }

    public function setQuestionnaireResponse(?QuestionnaireResponse $questionnaireResponse): static
    {
        $this->questionnaireResponse = $questionnaireResponse;
        return $this;
    }

    /**
     * Get the response value based on question type
     */
    public function getResponseValue(): mixed
    {
        if (!$this->question) {
            return null;
        }

        return match ($this->question->getType()) {
            Question::TYPE_TEXT, Question::TYPE_TEXTAREA, Question::TYPE_EMAIL => $this->textResponse,
            Question::TYPE_SINGLE_CHOICE, Question::TYPE_MULTIPLE_CHOICE => $this->choiceResponse,
            Question::TYPE_FILE_UPLOAD => $this->fileResponse,
            Question::TYPE_NUMBER => $this->numberResponse,
            Question::TYPE_DATE => $this->dateResponse,
            default => $this->textResponse
        };
    }

    /**
     * Set the response value based on question type
     */
    public function setResponseValue(mixed $value): static
    {
        if (!$this->question) {
            return $this;
        }

        switch ($this->question->getType()) {
            case Question::TYPE_TEXT:
            case Question::TYPE_TEXTAREA:
            case Question::TYPE_EMAIL:
                $this->textResponse = $value;
                break;
            case Question::TYPE_SINGLE_CHOICE:
            case Question::TYPE_MULTIPLE_CHOICE:
                $this->choiceResponse = is_array($value) ? $value : [$value];
                break;
            case Question::TYPE_FILE_UPLOAD:
                $this->fileResponse = $value;
                break;
            case Question::TYPE_NUMBER:
                $this->numberResponse = (int) $value;
                break;
            case Question::TYPE_DATE:
                if ($value instanceof \DateTimeInterface) {
                    $this->dateResponse = $value;
                } elseif (is_string($value)) {
                    $this->dateResponse = new \DateTime($value);
                }
                break;
            default:
                $this->textResponse = $value;
        }

        return $this;
    }

    /**
     * Check if the response has any answer
     */
    public function hasAnswer(): bool
    {
        $value = $this->getResponseValue();
        
        if ($value === null || $value === '') {
            return false;
        }
        
        if (is_array($value)) {
            return !empty($value);
        }
        
        return true;
    }

    /**
     * Get the response as formatted text for display
     */
    public function getFormattedResponse(): string
    {
        if (!$this->hasAnswer()) {
            return 'Pas de réponse';
        }

        $value = $this->getResponseValue();

        return match ($this->question->getType()) {
            Question::TYPE_SINGLE_CHOICE, Question::TYPE_MULTIPLE_CHOICE => $this->formatChoiceResponse(),
            Question::TYPE_FILE_UPLOAD => $this->fileResponse ? basename($this->fileResponse) : 'Aucun fichier',
            Question::TYPE_DATE => $this->dateResponse?->format('d/m/Y') ?? '',
            Question::TYPE_NUMBER => (string) $this->numberResponse,
            default => (string) $value
        };
    }

    /**
     * Format choice response for display
     */
    private function formatChoiceResponse(): string
    {
        if (!$this->choiceResponse || !$this->question) {
            return 'Aucune sélection';
        }

        $selectedOptions = [];
        foreach ($this->question->getOptions() as $option) {
            if (in_array($option->getId(), $this->choiceResponse)) {
                $selectedOptions[] = $option->getOptionText();
            }
        }

        return implode(', ', $selectedOptions);
    }

    /**
     * Calculate score for this response
     */
    public function calculateScore(): int
    {
        if (!$this->question || !$this->hasAnswer()) {
            return 0;
        }

        // For choice questions, calculate based on correct answers
        if ($this->question->hasChoices() && $this->question->hasCorrectAnswers()) {
            return $this->calculateChoiceScore();
        }

        // For other types, manual scoring might be needed
        // For now, return 0 if no automatic scoring is possible
        return $this->scoreEarned ?? 0;
    }

    /**
     * Calculate score for choice questions
     */
    private function calculateChoiceScore(): int
    {
        if (!$this->choiceResponse) {
            return 0;
        }

        $correctOptions = $this->question->getCorrectOptions();
        $correctOptionIds = $correctOptions->map(fn($option) => $option->getId())->toArray();
        
        // Calculate score based on correct selections
        $score = 0;
        $maxScore = $this->question->getPoints() ?? 0;
        
        if ($this->question->getType() === Question::TYPE_SINGLE_CHOICE) {
            // Single choice: full points if correct, 0 if wrong
            if (count($this->choiceResponse) === 1 && in_array($this->choiceResponse[0], $correctOptionIds)) {
                $score = $maxScore;
            }
        } else {
            // Multiple choice: proportional scoring
            $totalCorrect = count($correctOptionIds);
            $correctSelections = count(array_intersect($this->choiceResponse, $correctOptionIds));
            $incorrectSelections = count(array_diff($this->choiceResponse, $correctOptionIds));
            
            if ($totalCorrect > 0) {
                // Score = (correct selections - incorrect selections) / total correct * max points
                $score = max(0, (($correctSelections - $incorrectSelections) / $totalCorrect) * $maxScore);
            }
        }

        $this->scoreEarned = (int) $score;
        return $this->scoreEarned;
    }

    /**
     * Check if the response is correct (for QCM questions)
     */
    public function isCorrect(): bool
    {
        if (!$this->question || !$this->question->hasChoices() || !$this->question->hasCorrectAnswers()) {
            return false;
        }

        $correctOptions = $this->question->getCorrectOptions();
        $correctOptionIds = $correctOptions->map(fn($option) => $option->getId())->toArray();
        
        if (!$this->choiceResponse) {
            return false;
        }

        // Check if selected options match exactly with correct options
        sort($correctOptionIds);
        $selectedIds = $this->choiceResponse;
        sort($selectedIds);
        
        return $correctOptionIds === $selectedIds;
    }

    /**
     * Get file path for uploaded files
     */
    public function getFilePath(): ?string
    {
        if (!$this->fileResponse) {
            return null;
        }
        
        return 'uploads/questionnaire_files/' . $this->fileResponse;
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
        return $this->getFormattedResponse();
    }
}
