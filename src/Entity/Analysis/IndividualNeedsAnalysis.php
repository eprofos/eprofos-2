<?php

namespace App\Entity\Analysis;

use App\Repository\Analysis\IndividualNeedsAnalysisRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Individual Needs Analysis Entity
 * 
 * Represents a completed needs analysis for an individual, containing all
 * information required for Qualiopi 2.4 compliance including personal
 * details, professional status, funding information, and training requirements.
 */
#[ORM\Entity(repositoryClass: IndividualNeedsAnalysisRepository::class)]
#[ORM\Table(name: 'individual_needs_analyses')]
#[ORM\HasLifecycleCallbacks]
class IndividualNeedsAnalysis
{
    public const STATUS_EMPLOYEE = 'employee';
    public const STATUS_JOB_SEEKER = 'job_seeker';
    public const STATUS_OTHER = 'other';

    public const FUNDING_CPF = 'cpf';
    public const FUNDING_POLE_EMPLOI = 'pole_emploi';
    public const FUNDING_PERSONAL = 'personal';
    public const FUNDING_OTHER = 'other';

    public const LEVEL_BEGINNER = 'beginner';
    public const LEVEL_INTERMEDIATE = 'intermediate';
    public const LEVEL_ADVANCED = 'advanced';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: NeedsAnalysisRequest::class, inversedBy: 'individualAnalysis')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?NeedsAnalysisRequest $needsAnalysisRequest = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-ZÀ-ÿ\s\-\']+$/',
        message: 'Le prénom ne peut contenir que des lettres, espaces, tirets et apostrophes.'
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
    #[Assert\Regex(
        pattern: '/^[a-zA-ZÀ-ÿ\s\-\']+$/',
        message: 'Le nom ne peut contenir que des lettres, espaces, tirets et apostrophes.'
    )]
    private ?string $lastName = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'L\'adresse est obligatoire.')]
    #[Assert\Length(
        min: 10,
        max: 1000,
        minMessage: 'L\'adresse doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'L\'adresse ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $address = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le téléphone est obligatoire.')]
    #[Assert\Regex(
        pattern: '/^(?:\+33|0)[1-9](?:[0-9]{8})$/',
        message: 'Veuillez saisir un numéro de téléphone français valide.'
    )]
    private ?string $phone = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
    #[Assert\Email(message: 'Veuillez saisir une adresse email valide.')]
    #[Assert\Length(
        max: 180,
        maxMessage: 'L\'email ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $email = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le statut professionnel est obligatoire.')]
    #[Assert\Choice(
        choices: [self::STATUS_EMPLOYEE, self::STATUS_JOB_SEEKER, self::STATUS_OTHER],
        message: 'Veuillez choisir un statut professionnel valide.'
    )]
    private ?string $status = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 500,
        maxMessage: 'Les détails du statut ne peuvent pas dépasser {{ limit }} caractères.'
    )]
    private ?string $statusOtherDetails = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le type de financement est obligatoire.')]
    #[Assert\Choice(
        choices: [self::FUNDING_CPF, self::FUNDING_POLE_EMPLOI, self::FUNDING_PERSONAL, self::FUNDING_OTHER],
        message: 'Veuillez choisir un type de financement valide.'
    )]
    private ?string $fundingType = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 500,
        maxMessage: 'Les détails du financement ne peuvent pas dépasser {{ limit }} caractères.'
    )]
    private ?string $fundingOtherDetails = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'L\'intitulé de la formation souhaitée est obligatoire.')]
    #[Assert\Length(
        min: 5,
        max: 255,
        minMessage: 'L\'intitulé de la formation doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'L\'intitulé de la formation ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $desiredTrainingTitle = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'L\'objectif professionnel est obligatoire.')]
    #[Assert\Length(
        min: 20,
        max: 1000,
        minMessage: 'L\'objectif professionnel doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'L\'objectif professionnel ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $professionalObjective = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le niveau actuel est obligatoire.')]
    #[Assert\Choice(
        choices: [self::LEVEL_BEGINNER, self::LEVEL_INTERMEDIATE, self::LEVEL_ADVANCED],
        message: 'Veuillez choisir un niveau valide.'
    )]
    private ?string $currentLevel = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'La durée souhaitée est obligatoire.')]
    #[Assert\Positive(message: 'La durée souhaitée doit être positive.')]
    #[Assert\Range(
        min: 1,
        max: 2000,
        notInRangeMessage: 'La durée souhaitée doit être entre {{ min }} et {{ max }} heures.'
    )]
    private ?int $desiredDurationHours = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $preferredStartDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $preferredEndDate = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'La préférence de lieu de formation est obligatoire.')]
    #[Assert\Choice(
        choices: ['on_site', 'remote', 'hybrid', 'training_center'],
        message: 'Veuillez choisir une option valide pour le lieu de formation.'
    )]
    private ?string $trainingLocationPreference = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'Les accommodations handicap ne peuvent pas dépasser {{ limit }} caractères.'
    )]
    private ?string $disabilityAccommodations = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Les attentes de formation sont obligatoires.')]
    #[Assert\Length(
        min: 20,
        max: 2000,
        minMessage: 'Les attentes de formation doivent contenir au moins {{ limit }} caractères.',
        maxMessage: 'Les attentes de formation ne peuvent pas dépasser {{ limit }} caractères.'
    )]
    private ?string $trainingExpectations = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Les besoins spécifiques sont obligatoires.')]
    #[Assert\Length(
        min: 10,
        max: 2000,
        minMessage: 'Les besoins spécifiques doivent contenir au moins {{ limit }} caractères.',
        maxMessage: 'Les besoins spécifiques ne peuvent pas dépasser {{ limit }} caractères.'
    )]
    private ?string $specificNeeds = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $submittedAt = null;

    public function __construct()
    {
        $this->submittedAt = new \DateTimeImmutable();
    }

    /**
     * Lifecycle callback executed before persisting the entity
     */
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if ($this->submittedAt === null) {
            $this->submittedAt = new \DateTimeImmutable();
        }
    }

    /**
     * Get the full name of the individual
     */
    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    /**
     * Get status label for display
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_EMPLOYEE => 'Salarié',
            self::STATUS_JOB_SEEKER => 'Demandeur d\'emploi',
            self::STATUS_OTHER => 'Autre',
            default => 'Non spécifié'
        };
    }

    /**
     * Get funding type label for display
     */
    public function getFundingTypeLabel(): string
    {
        return match ($this->fundingType) {
            self::FUNDING_CPF => 'CPF (Compte Personnel de Formation)',
            self::FUNDING_POLE_EMPLOI => 'Pôle Emploi',
            self::FUNDING_PERSONAL => 'Fonds propres',
            self::FUNDING_OTHER => 'Autre',
            default => 'Non spécifié'
        };
    }

    /**
     * Get current level label for display
     */
    public function getCurrentLevelLabel(): string
    {
        return match ($this->currentLevel) {
            self::LEVEL_BEGINNER => 'Débutant',
            self::LEVEL_INTERMEDIATE => 'Intermédiaire',
            self::LEVEL_ADVANCED => 'Avancé',
            default => 'Non spécifié'
        };
    }

    /**
     * Get training location preference label
     */
    public function getTrainingLocationPreferenceLabel(): string
    {
        return match ($this->trainingLocationPreference) {
            'on_site' => 'Sur site',
            'remote' => 'À distance',
            'hybrid' => 'Hybride (présentiel + distanciel)',
            'training_center' => 'Centre de formation',
            default => 'Non spécifié'
        };
    }

    /**
     * Get formatted duration as human readable string
     */
    public function getFormattedDuration(): string
    {
        if ($this->desiredDurationHours === null) {
            return '';
        }

        if ($this->desiredDurationHours < 8) {
            return $this->desiredDurationHours . 'h';
        }

        $days = intval($this->desiredDurationHours / 8);
        $remainingHours = $this->desiredDurationHours % 8;

        if ($remainingHours === 0) {
            return $days . ' jour' . ($days > 1 ? 's' : '');
        }

        return $days . ' jour' . ($days > 1 ? 's' : '') . ' ' . $remainingHours . 'h';
    }

    /**
     * Validate dates consistency
     */
    #[Assert\Callback]
    public function validateDates(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        if ($this->preferredStartDate && $this->preferredEndDate) {
            if ($this->preferredStartDate > $this->preferredEndDate) {
                $context->buildViolation('La date de fin doit être postérieure à la date de début.')
                    ->atPath('preferredEndDate')
                    ->addViolation();
            }
        }
    }

    /**
     * Validate status other details requirement
     */
    #[Assert\Callback]
    public function validateStatusOtherDetails(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        if ($this->status === self::STATUS_OTHER && empty($this->statusOtherDetails)) {
            $context->buildViolation('Veuillez préciser votre statut professionnel.')
                ->atPath('statusOtherDetails')
                ->addViolation();
        }
    }

    /**
     * Validate funding other details requirement
     */
    #[Assert\Callback]
    public function validateFundingOtherDetails(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        if ($this->fundingType === self::FUNDING_OTHER && empty($this->fundingOtherDetails)) {
            $context->buildViolation('Veuillez préciser votre type de financement.')
                ->atPath('fundingOtherDetails')
                ->addViolation();
        }
    }

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNeedsAnalysisRequest(): ?NeedsAnalysisRequest
    {
        return $this->needsAnalysisRequest;
    }

    public function setNeedsAnalysisRequest(?NeedsAnalysisRequest $needsAnalysisRequest): static
    {
        $this->needsAnalysisRequest = $needsAnalysisRequest;
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

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;
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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getStatusOtherDetails(): ?string
    {
        return $this->statusOtherDetails;
    }

    public function setStatusOtherDetails(?string $statusOtherDetails): static
    {
        $this->statusOtherDetails = $statusOtherDetails;
        return $this;
    }

    public function getFundingType(): ?string
    {
        return $this->fundingType;
    }

    public function setFundingType(string $fundingType): static
    {
        $this->fundingType = $fundingType;
        return $this;
    }

    public function getFundingOtherDetails(): ?string
    {
        return $this->fundingOtherDetails;
    }

    public function setFundingOtherDetails(?string $fundingOtherDetails): static
    {
        $this->fundingOtherDetails = $fundingOtherDetails;
        return $this;
    }

    public function getDesiredTrainingTitle(): ?string
    {
        return $this->desiredTrainingTitle;
    }

    public function setDesiredTrainingTitle(string $desiredTrainingTitle): static
    {
        $this->desiredTrainingTitle = $desiredTrainingTitle;
        return $this;
    }

    public function getProfessionalObjective(): ?string
    {
        return $this->professionalObjective;
    }

    public function setProfessionalObjective(string $professionalObjective): static
    {
        $this->professionalObjective = $professionalObjective;
        return $this;
    }

    public function getCurrentLevel(): ?string
    {
        return $this->currentLevel;
    }

    public function setCurrentLevel(string $currentLevel): static
    {
        $this->currentLevel = $currentLevel;
        return $this;
    }

    public function getDesiredDurationHours(): ?int
    {
        return $this->desiredDurationHours;
    }

    public function setDesiredDurationHours(int $desiredDurationHours): static
    {
        $this->desiredDurationHours = $desiredDurationHours;
        return $this;
    }

    public function getPreferredStartDate(): ?\DateTimeInterface
    {
        return $this->preferredStartDate;
    }

    public function setPreferredStartDate(?\DateTimeInterface $preferredStartDate): static
    {
        $this->preferredStartDate = $preferredStartDate;
        return $this;
    }

    public function getPreferredEndDate(): ?\DateTimeInterface
    {
        return $this->preferredEndDate;
    }

    public function setPreferredEndDate(?\DateTimeInterface $preferredEndDate): static
    {
        $this->preferredEndDate = $preferredEndDate;
        return $this;
    }

    public function getTrainingLocationPreference(): ?string
    {
        return $this->trainingLocationPreference;
    }

    public function setTrainingLocationPreference(string $trainingLocationPreference): static
    {
        $this->trainingLocationPreference = $trainingLocationPreference;
        return $this;
    }

    public function getDisabilityAccommodations(): ?string
    {
        return $this->disabilityAccommodations;
    }

    public function setDisabilityAccommodations(?string $disabilityAccommodations): static
    {
        $this->disabilityAccommodations = $disabilityAccommodations;
        return $this;
    }

    public function getTrainingExpectations(): ?string
    {
        return $this->trainingExpectations;
    }

    public function setTrainingExpectations(string $trainingExpectations): static
    {
        $this->trainingExpectations = $trainingExpectations;
        return $this;
    }

    public function getSpecificNeeds(): ?string
    {
        return $this->specificNeeds;
    }

    public function setSpecificNeeds(string $specificNeeds): static
    {
        $this->specificNeeds = $specificNeeds;
        return $this;
    }

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(\DateTimeImmutable $submittedAt): static
    {
        $this->submittedAt = $submittedAt;
        return $this;
    }

    public function __toString(): string
    {
        return sprintf(
            '%s - %s (%s)',
            $this->getFullName(),
            $this->desiredTrainingTitle ?? 'Formation non spécifiée',
            $this->getStatusLabel()
        );
    }
}