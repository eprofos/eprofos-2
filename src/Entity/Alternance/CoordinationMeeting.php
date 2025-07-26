<?php

namespace App\Entity\Alternance;

use App\Entity\User\Mentor;
use App\Entity\User\Student;
use App\Entity\User\Teacher;
use App\Repository\CoordinationMeetingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * CoordinationMeeting entity for managing coordination meetings between training center and companies
 * 
 * Represents coordination meetings between pedagogical supervisors and company mentors
 * for apprenticeship follow-up, essential for Qualiopi compliance regarding optimal coordination.
 */
#[ORM\Entity(repositoryClass: CoordinationMeetingRepository::class)]
#[ORM\Table(name: 'coordination_meetings')]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\Loggable]
#[ORM\Index(columns: ['student_id'], name: 'idx_coordination_student')]
#[ORM\Index(columns: ['date'], name: 'idx_coordination_date')]
#[ORM\Index(columns: ['status'], name: 'idx_coordination_status')]
#[ORM\Index(columns: ['type'], name: 'idx_coordination_type')]
class CoordinationMeeting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Student::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'L\'alternant concerné est obligatoire.')]
    private ?Student $student = null;

    #[ORM\ManyToOne(targetEntity: Teacher::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: 'Le référent pédagogique est obligatoire.')]
    private ?Teacher $pedagogicalSupervisor = null;

    #[ORM\ManyToOne(targetEntity: Mentor::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: 'Le tuteur entreprise est obligatoire.')]
    private ?Mentor $mentor = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'La date de réunion est obligatoire.')]
    #[Assert\GreaterThan(
        'today',
        message: 'La date de réunion doit être dans le futur.',
        groups: ['creation']
    )]
    #[Gedmo\Versioned]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le type de réunion est obligatoire.')]
    #[Assert\Choice(
        choices: [
            self::TYPE_PREPARATORY,
            self::TYPE_FOLLOW_UP,
            self::TYPE_EVALUATION,
            self::TYPE_PROBLEM_SOLVING,
            self::TYPE_ORIENTATION
        ],
        message: 'Type de réunion invalide.'
    )]
    #[Gedmo\Versioned]
    private ?string $type = self::TYPE_FOLLOW_UP;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le lieu de réunion est obligatoire.')]
    #[Assert\Choice(
        choices: [
            self::LOCATION_TRAINING_CENTER,
            self::LOCATION_COMPANY,
            self::LOCATION_VIDEO_CONFERENCE,
            self::LOCATION_PHONE
        ],
        message: 'Lieu de réunion invalide.'
    )]
    #[Gedmo\Versioned]
    private ?string $location = self::LOCATION_VIDEO_CONFERENCE;

    #[ORM\Column(type: Types::JSON)]
    #[Assert\Type(type: 'array', message: 'L\'ordre du jour doit être un tableau.')]
    #[Gedmo\Versioned]
    private array $agenda = [];

    #[ORM\Column(type: Types::JSON)]
    #[Assert\Type(type: 'array', message: 'Les points de discussion doivent être un tableau.')]
    #[Gedmo\Versioned]
    private array $discussionPoints = [];

    #[ORM\Column(type: Types::JSON)]
    #[Assert\Type(type: 'array', message: 'Les décisions doivent être un tableau.')]
    #[Gedmo\Versioned]
    private array $decisions = [];

    #[ORM\Column(type: Types::JSON)]
    #[Assert\Type(type: 'array', message: 'Le plan d\'actions doit être un tableau.')]
    #[Gedmo\Versioned]
    private array $actionPlan = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Assert\GreaterThan(
        propertyPath: 'date',
        message: 'La prochaine réunion doit être postérieure à la réunion actuelle.'
    )]
    #[Gedmo\Versioned]
    private ?\DateTimeInterface $nextMeetingDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 5000,
        maxMessage: 'Le compte-rendu ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Gedmo\Versioned]
    private ?string $meetingReport = null;

    #[ORM\Column(length: 30)]
    #[Assert\Choice(
        choices: [
            self::STATUS_PLANNED,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_POSTPONED
        ],
        message: 'Statut de réunion invalide.'
    )]
    #[Gedmo\Versioned]
    private ?string $status = self::STATUS_PLANNED;

    #[ORM\Column(type: Types::JSON)]
    #[Assert\Type(type: 'array', message: 'Les participants doivent être un tableau.')]
    #[Gedmo\Versioned]
    private array $attendees = [];

    #[ORM\Column(nullable: true)]
    #[Assert\Positive(message: 'La durée doit être positive.')]
    #[Assert\LessThan(480, message: 'La durée ne peut pas dépasser 8 heures (480 minutes).')]
    #[Gedmo\Versioned]
    private ?int $duration = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'Les notes ne peuvent pas dépasser {{ limit }} caractères.'
    )]
    private ?string $notes = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(
        min: 1,
        max: 5,
        notInRangeMessage: 'La satisfaction doit être comprise entre {{ min }} et {{ max }}.'
    )]
    private ?int $satisfactionRating = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $createdBy = null;

    // Meeting type constants
    public const TYPE_PREPARATORY = 'preparatory';
    public const TYPE_FOLLOW_UP = 'follow_up';
    public const TYPE_EVALUATION = 'evaluation';
    public const TYPE_PROBLEM_SOLVING = 'problem_solving';
    public const TYPE_ORIENTATION = 'orientation';

    // Location constants
    public const LOCATION_TRAINING_CENTER = 'training_center';
    public const LOCATION_COMPANY = 'company';
    public const LOCATION_VIDEO_CONFERENCE = 'video_conference';
    public const LOCATION_PHONE = 'phone';

    // Status constants
    public const STATUS_PLANNED = 'planned';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_POSTPONED = 'postponed';

    // Meeting types labels
    public const TYPE_LABELS = [
        self::TYPE_PREPARATORY => 'Réunion préparatoire',
        self::TYPE_FOLLOW_UP => 'Réunion de suivi',
        self::TYPE_EVALUATION => 'Réunion d\'évaluation',
        self::TYPE_PROBLEM_SOLVING => 'Résolution de problème',
        self::TYPE_ORIENTATION => 'Réunion d\'orientation'
    ];

    // Location labels
    public const LOCATION_LABELS = [
        self::LOCATION_TRAINING_CENTER => 'Centre de formation',
        self::LOCATION_COMPANY => 'Entreprise',
        self::LOCATION_VIDEO_CONFERENCE => 'Visioconférence',
        self::LOCATION_PHONE => 'Téléphone'
    ];

    // Status labels
    public const STATUS_LABELS = [
        self::STATUS_PLANNED => 'Planifiée',
        self::STATUS_COMPLETED => 'Réalisée',
        self::STATUS_CANCELLED => 'Annulée',
        self::STATUS_POSTPONED => 'Reportée'
    ];

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->agenda = [];
        $this->discussionPoints = [];
        $this->decisions = [];
        $this->actionPlan = [];
        $this->attendees = [];
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

    public function getPedagogicalSupervisor(): ?Teacher
    {
        return $this->pedagogicalSupervisor;
    }

    public function setPedagogicalSupervisor(?Teacher $pedagogicalSupervisor): static
    {
        $this->pedagogicalSupervisor = $pedagogicalSupervisor;
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

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(?\DateTimeInterface $date): static
    {
        $this->date = $date;
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

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(string $location): static
    {
        $this->location = $location;
        return $this;
    }

    public function getAgenda(): array
    {
        return $this->agenda;
    }

    public function setAgenda(array $agenda): static
    {
        $this->agenda = $agenda;
        return $this;
    }

    public function getDiscussionPoints(): array
    {
        return $this->discussionPoints;
    }

    public function setDiscussionPoints(array $discussionPoints): static
    {
        $this->discussionPoints = $discussionPoints;
        return $this;
    }

    public function getDecisions(): array
    {
        return $this->decisions;
    }

    public function setDecisions(array $decisions): static
    {
        $this->decisions = $decisions;
        return $this;
    }

    public function getActionPlan(): array
    {
        return $this->actionPlan;
    }

    public function setActionPlan(array $actionPlan): static
    {
        $this->actionPlan = $actionPlan;
        return $this;
    }

    public function getNextMeetingDate(): ?\DateTimeInterface
    {
        return $this->nextMeetingDate;
    }

    public function setNextMeetingDate(?\DateTimeInterface $nextMeetingDate): static
    {
        $this->nextMeetingDate = $nextMeetingDate;
        return $this;
    }

    public function getMeetingReport(): ?string
    {
        return $this->meetingReport;
    }

    public function setMeetingReport(?string $meetingReport): static
    {
        $this->meetingReport = $meetingReport;
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

    public function getAttendees(): array
    {
        return $this->attendees;
    }

    public function setAttendees(array $attendees): static
    {
        $this->attendees = $attendees;
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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getSatisfactionRating(): ?int
    {
        return $this->satisfactionRating;
    }

    public function setSatisfactionRating(?int $satisfactionRating): static
    {
        $this->satisfactionRating = $satisfactionRating;
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
     * Get type label for display
     */
    public function getTypeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    /**
     * Get location label for display
     */
    public function getLocationLabel(): string
    {
        return self::LOCATION_LABELS[$this->location] ?? $this->location;
    }

    /**
     * Get status label for display
     */
    public function getStatusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * Get status badge class for display
     */
    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_PLANNED => 'bg-primary',
            self::STATUS_COMPLETED => 'bg-success',
            self::STATUS_CANCELLED => 'bg-danger',
            self::STATUS_POSTPONED => 'bg-warning',
            default => 'bg-secondary'
        };
    }

    /**
     * Check if meeting is upcoming
     */
    public function isUpcoming(): bool
    {
        if (!$this->date) {
            return false;
        }
        
        return $this->status === self::STATUS_PLANNED && $this->date > new \DateTime();
    }

    /**
     * Check if meeting is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if meeting can be edited
     */
    public function canBeEdited(): bool
    {
        return in_array($this->status, [self::STATUS_PLANNED, self::STATUS_POSTPONED]);
    }

    /**
     * Add agenda item
     */
    public function addAgendaItem(string $item): static
    {
        $this->agenda[] = $item;
        return $this;
    }

    /**
     * Remove agenda item
     */
    public function removeAgendaItem(int $index): static
    {
        if (isset($this->agenda[$index])) {
            unset($this->agenda[$index]);
            $this->agenda = array_values($this->agenda); // Reindex
        }
        return $this;
    }

    /**
     * Add discussion point
     */
    public function addDiscussionPoint(string $point): static
    {
        $this->discussionPoints[] = $point;
        return $this;
    }

    /**
     * Add decision
     */
    public function addDecision(string $decision): static
    {
        $this->decisions[] = $decision;
        return $this;
    }

    /**
     * Add action item to plan
     */
    public function addActionItem(array $actionItem): static
    {
        $this->actionPlan[] = $actionItem;
        return $this;
    }

    /**
     * Add attendee
     */
    public function addAttendee(array $attendee): static
    {
        $this->attendees[] = $attendee;
        return $this;
    }

    /**
     * Mark as completed
     */
    public function markCompleted(): static
    {
        $this->status = self::STATUS_COMPLETED;
        return $this;
    }

    /**
     * Mark as cancelled
     */
    public function markCancelled(): static
    {
        $this->status = self::STATUS_CANCELLED;
        return $this;
    }

    /**
     * Postpone meeting
     */
    public function postpone(\DateTimeInterface $newDate): static
    {
        $this->status = self::STATUS_POSTPONED;
        $this->date = $newDate;
        return $this;
    }

    /**
     * Get meeting duration in human readable format
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
     * Get satisfaction rating with stars
     */
    public function getSatisfactionStars(): string
    {
        if (!$this->satisfactionRating) {
            return 'Non évalué';
        }

        return str_repeat('★', $this->satisfactionRating) . str_repeat('☆', 5 - $this->satisfactionRating);
    }

    /**
     * Get meeting summary for notifications
     */
    public function getMeetingSummary(): string
    {
        return sprintf(
            '%s - %s avec %s le %s',
            $this->getTypeLabel(),
            $this->student?->getFullName() ?? 'Alternant',
            $this->mentor?->getFullName() ?? 'Tuteur',
            $this->date?->format('d/m/Y à H:i') ?? 'Date non définie'
        );
    }

    /**
     * Check if meeting requires follow-up
     */
    public function requiresFollowUp(): bool
    {
        return $this->isCompleted() && 
               (count($this->actionPlan) > 0 || $this->nextMeetingDate !== null);
    }

    /**
     * Calculate time until meeting
     */
    public function getTimeUntilMeeting(): ?\DateInterval
    {
        if (!$this->date || !$this->isUpcoming()) {
            return null;
        }

        $now = new \DateTime();
        return $now->diff($this->date);
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
        return $this->getMeetingSummary();
    }
}
