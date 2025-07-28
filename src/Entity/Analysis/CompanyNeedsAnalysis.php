<?php

declare(strict_types=1);

namespace App\Entity\Analysis;

use App\Repository\Analysis\CompanyNeedsAnalysisRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Company Needs Analysis Entity.
 *
 * Represents a completed needs analysis for a company, containing all
 * information required for Qualiopi 2.4 compliance including company
 * details, trainee information, training requirements, and logistics.
 */
#[ORM\Entity(repositoryClass: CompanyNeedsAnalysisRepository::class)]
#[ORM\Table(name: 'company_needs_analyses')]
#[ORM\HasLifecycleCallbacks]
class CompanyNeedsAnalysis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: NeedsAnalysisRequest::class, inversedBy: 'companyAnalysis')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?NeedsAnalysisRequest $needsAnalysisRequest = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom de l\'entreprise est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le nom de l\'entreprise doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom de l\'entreprise ne peut pas dépasser {{ limit }} caractères.',
    )]
    private ?string $companyName = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom du responsable est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le nom du responsable doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom du responsable ne peut pas dépasser {{ limit }} caractères.',
    )]
    private ?string $responsiblePerson = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'L\'email de contact est obligatoire.')]
    #[Assert\Email(message: 'Veuillez saisir une adresse email valide.')]
    #[Assert\Length(
        max: 180,
        maxMessage: 'L\'email ne peut pas dépasser {{ limit }} caractères.',
    )]
    private ?string $contactEmail = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le téléphone de contact est obligatoire.')]
    #[Assert\Regex(
        pattern: '/^(?:\+33|0)[1-9](?:[0-9]{8})$/',
        message: 'Veuillez saisir un numéro de téléphone français valide.',
    )]
    private ?string $contactPhone = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'L\'adresse de l\'entreprise est obligatoire.')]
    #[Assert\Length(
        min: 10,
        max: 1000,
        minMessage: 'L\'adresse doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'L\'adresse ne peut pas dépasser {{ limit }} caractères.',
    )]
    private ?string $companyAddress = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le secteur d\'activité est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le secteur d\'activité doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le secteur d\'activité ne peut pas dépasser {{ limit }} caractères.',
    )]
    private ?string $activitySector = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\Regex(
        pattern: '/^\d{4}[A-Z]$/',
        message: 'Le code NAF doit être au format 1234A.',
    )]
    private ?string $nafCode = null;

    #[ORM\Column(length: 14, nullable: true)]
    #[Assert\Regex(
        pattern: '/^\d{14}$/',
        message: 'Le SIRET doit contenir exactement 14 chiffres.',
    )]
    private ?string $siret = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Le nombre de salariés est obligatoire.')]
    #[Assert\Positive(message: 'Le nombre de salariés doit être positif.')]
    #[Assert\Range(
        min: 1,
        max: 999999,
        notInRangeMessage: 'Le nombre de salariés doit être entre {{ min }} et {{ max }}.',
    )]
    private ?int $employeeCount = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Le nom de l\'OPCO ne peut pas dépasser {{ limit }} caractères.',
    )]
    private ?string $opco = null;

    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotBlank(message: 'Les informations des stagiaires sont obligatoires.')]
    #[Assert\Count(
        min: 1,
        minMessage: 'Au moins un stagiaire doit être renseigné.',
    )]
    private array $traineesInfo = [];

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'L\'intitulé de la formation est obligatoire.')]
    #[Assert\Length(
        min: 5,
        max: 255,
        minMessage: 'L\'intitulé de la formation doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'L\'intitulé de la formation ne peut pas dépasser {{ limit }} caractères.',
    )]
    private ?string $trainingTitle = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'La durée de formation est obligatoire.')]
    #[Assert\Positive(message: 'La durée de formation doit être positive.')]
    #[Assert\Range(
        min: 1,
        max: 2000,
        notInRangeMessage: 'La durée de formation doit être entre {{ min }} et {{ max }} heures.',
    )]
    private ?int $trainingDurationHours = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?DateTimeInterface $preferredStartDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?DateTimeInterface $preferredEndDate = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'La préférence de lieu de formation est obligatoire.')]
    #[Assert\Choice(
        choices: ['on_site', 'remote', 'hybrid', 'training_center'],
        message: 'Veuillez choisir une option valide pour le lieu de formation.',
    )]
    private ?string $trainingLocationPreference = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'Les besoins d\'appropriation ne peuvent pas dépasser {{ limit }} caractères.',
    )]
    private ?string $locationAppropriationNeeds = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'Les accommodations handicap ne peuvent pas dépasser {{ limit }} caractères.',
    )]
    private ?string $disabilityAccommodations = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Les attentes de formation sont obligatoires.')]
    #[Assert\Length(
        min: 20,
        max: 2000,
        minMessage: 'Les attentes de formation doivent contenir au moins {{ limit }} caractères.',
        maxMessage: 'Les attentes de formation ne peuvent pas dépasser {{ limit }} caractères.',
    )]
    private ?string $trainingExpectations = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Les besoins spécifiques sont obligatoires.')]
    #[Assert\Length(
        min: 10,
        max: 2000,
        minMessage: 'Les besoins spécifiques doivent contenir au moins {{ limit }} caractères.',
        maxMessage: 'Les besoins spécifiques ne peuvent pas dépasser {{ limit }} caractères.',
    )]
    private ?string $specificNeeds = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?DateTimeImmutable $submittedAt = null;

    public function __construct()
    {
        $this->submittedAt = new DateTimeImmutable();
    }

    public function __toString(): string
    {
        return sprintf(
            '%s - %s (%d stagiaires)',
            $this->companyName ?? 'Entreprise inconnue',
            $this->trainingTitle ?? 'Formation non spécifiée',
            $this->getTraineesCount(),
        );
    }

    /**
     * Lifecycle callback executed before persisting the entity.
     */
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if ($this->submittedAt === null) {
            $this->submittedAt = new DateTimeImmutable();
        }
    }

    /**
     * Get training location preference label.
     */
    public function getTrainingLocationPreferenceLabel(): string
    {
        return match ($this->trainingLocationPreference) {
            'on_site' => 'Sur site (dans l\'entreprise)',
            'remote' => 'À distance',
            'hybrid' => 'Hybride (présentiel + distanciel)',
            'training_center' => 'Centre de formation',
            default => 'Non spécifié'
        };
    }

    /**
     * Add a trainee to the trainees info array.
     */
    public function addTrainee(string $firstName, string $lastName, string $position): void
    {
        $this->traineesInfo[] = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'position' => $position,
        ];
    }

    /**
     * Remove a trainee from the trainees info array.
     */
    public function removeTrainee(int $index): void
    {
        if (isset($this->traineesInfo[$index])) {
            unset($this->traineesInfo[$index]);
            $this->traineesInfo = array_values($this->traineesInfo); // Reindex array
        }
    }

    /**
     * Get the number of trainees.
     */
    public function getTraineesCount(): int
    {
        return count($this->traineesInfo);
    }

    /**
     * Get formatted trainees list for display.
     */
    public function getFormattedTraineesList(): string
    {
        if (empty($this->traineesInfo)) {
            return 'Aucun stagiaire renseigné';
        }

        $trainees = [];
        foreach ($this->traineesInfo as $trainee) {
            $trainees[] = sprintf(
                '%s %s (%s)',
                $trainee['first_name'] ?? '',
                $trainee['last_name'] ?? '',
                $trainee['position'] ?? 'Poste non spécifié',
            );
        }

        return implode(', ', $trainees);
    }

    /**
     * Validate dates consistency.
     */
    #[Assert\Callback]
    public function validateDates(ExecutionContextInterface $context): void
    {
        if ($this->preferredStartDate && $this->preferredEndDate) {
            if ($this->preferredStartDate > $this->preferredEndDate) {
                $context->buildViolation('La date de fin doit être postérieure à la date de début.')
                    ->atPath('preferredEndDate')
                    ->addViolation()
                ;
            }
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

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(string $companyName): static
    {
        $this->companyName = $companyName;

        return $this;
    }

    public function getResponsiblePerson(): ?string
    {
        return $this->responsiblePerson;
    }

    public function setResponsiblePerson(string $responsiblePerson): static
    {
        $this->responsiblePerson = $responsiblePerson;

        return $this;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(string $contactEmail): static
    {
        $this->contactEmail = $contactEmail;

        return $this;
    }

    public function getContactPhone(): ?string
    {
        return $this->contactPhone;
    }

    public function setContactPhone(string $contactPhone): static
    {
        $this->contactPhone = $contactPhone;

        return $this;
    }

    public function getCompanyAddress(): ?string
    {
        return $this->companyAddress;
    }

    public function setCompanyAddress(string $companyAddress): static
    {
        $this->companyAddress = $companyAddress;

        return $this;
    }

    public function getActivitySector(): ?string
    {
        return $this->activitySector;
    }

    public function setActivitySector(string $activitySector): static
    {
        $this->activitySector = $activitySector;

        return $this;
    }

    public function getNafCode(): ?string
    {
        return $this->nafCode;
    }

    public function setNafCode(?string $nafCode): static
    {
        $this->nafCode = $nafCode;

        return $this;
    }

    public function getSiret(): ?string
    {
        return $this->siret;
    }

    public function setSiret(?string $siret): static
    {
        $this->siret = $siret;

        return $this;
    }

    public function getEmployeeCount(): ?int
    {
        return $this->employeeCount;
    }

    public function setEmployeeCount(int $employeeCount): static
    {
        $this->employeeCount = $employeeCount;

        return $this;
    }

    public function getOpco(): ?string
    {
        return $this->opco;
    }

    public function setOpco(?string $opco): static
    {
        $this->opco = $opco;

        return $this;
    }

    public function getTraineesInfo(): array
    {
        return $this->traineesInfo;
    }

    public function setTraineesInfo(array $traineesInfo): static
    {
        $this->traineesInfo = $traineesInfo;

        return $this;
    }

    public function getTrainingTitle(): ?string
    {
        return $this->trainingTitle;
    }

    public function setTrainingTitle(string $trainingTitle): static
    {
        $this->trainingTitle = $trainingTitle;

        return $this;
    }

    public function getTrainingDurationHours(): ?int
    {
        return $this->trainingDurationHours;
    }

    public function setTrainingDurationHours(int $trainingDurationHours): static
    {
        $this->trainingDurationHours = $trainingDurationHours;

        return $this;
    }

    public function getPreferredStartDate(): ?DateTimeInterface
    {
        return $this->preferredStartDate;
    }

    public function setPreferredStartDate(?DateTimeInterface $preferredStartDate): static
    {
        $this->preferredStartDate = $preferredStartDate;

        return $this;
    }

    public function getPreferredEndDate(): ?DateTimeInterface
    {
        return $this->preferredEndDate;
    }

    public function setPreferredEndDate(?DateTimeInterface $preferredEndDate): static
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

    public function getLocationAppropriationNeeds(): ?string
    {
        return $this->locationAppropriationNeeds;
    }

    public function setLocationAppropriationNeeds(?string $locationAppropriationNeeds): static
    {
        $this->locationAppropriationNeeds = $locationAppropriationNeeds;

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

    public function getSubmittedAt(): ?DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(DateTimeImmutable $submittedAt): static
    {
        $this->submittedAt = $submittedAt;

        return $this;
    }
}
