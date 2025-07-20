<?php

namespace App\Entity;

use App\Entity\Training\Session;
use App\Repository\AttendanceRecordRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * AttendanceRecord entity for tracking student attendance in training sessions
 * 
 * Essential for Qualiopi Criterion 12 compliance - provides detailed attendance tracking,
 * participation scoring, and absence management for all training sessions.
 */
#[ORM\Entity(repositoryClass: AttendanceRecordRepository::class)]
#[ORM\Table(name: 'attendance_records')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['student_id', 'session_id'], name: 'idx_student_session')]
#[ORM\Index(columns: ['status'], name: 'idx_status')]
#[ORM\Index(columns: ['recorded_at'], name: 'idx_recorded_at')]
#[ORM\UniqueConstraint(columns: ['student_id', 'session_id'], name: 'unique_student_session')]
class AttendanceRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Student::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Student $student = null;

    #[ORM\ManyToOne(targetEntity: Session::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Session $session = null;

    /**
     * Attendance status
     */
    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le statut de présence est obligatoire')]
    #[Assert\Choice(
        choices: [self::STATUS_PRESENT, self::STATUS_ABSENT, self::STATUS_LATE, self::STATUS_PARTIAL],
        message: 'Statut de présence invalide'
    )]
    private ?string $status = self::STATUS_PRESENT;

    /**
     * Participation score (0-10)
     */
    #[ORM\Column]
    #[Assert\Range(
        min: 0,
        max: 10,
        notInRangeMessage: 'Le score de participation doit être entre {{ min }} et {{ max }}'
    )]
    private ?int $participationScore = 5;

    /**
     * Reason for absence (if applicable)
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 500,
        maxMessage: 'La raison d\'absence ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $absenceReason = null;

    /**
     * Whether the absence is excused
     */
    #[ORM\Column]
    private ?bool $excused = false;

    /**
     * Administrative notes about attendance
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'Les notes administratives ne peuvent pas dépasser {{ limit }} caractères'
    )]
    private ?string $adminNotes = null;

    /**
     * Time when student arrived (for late arrivals)
     */
    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $arrivalTime = null;

    /**
     * Time when student left (for early departures)
     */
    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $departureTime = null;

    /**
     * Minutes late (calculated field)
     */
    #[ORM\Column(nullable: true)]
    private ?int $minutesLate = null;

    /**
     * Minutes of early departure (calculated field)
     */
    #[ORM\Column(nullable: true)]
    private ?int $minutesEarlyDeparture = null;

    /**
     * Additional metadata for attendance tracking
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    /**
     * When this attendance record was created/recorded
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $recordedAt = null;

    /**
     * Who recorded this attendance (admin user)
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $recordedBy = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    // Status constants
    public const STATUS_PRESENT = 'present';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_LATE = 'late';
    public const STATUS_PARTIAL = 'partial';

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->recordedAt = new \DateTime();
        $this->metadata = [];
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

    public function getSession(): ?Session
    {
        return $this->session;
    }

    public function setSession(?Session $session): static
    {
        $this->session = $session;
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

    public function getParticipationScore(): ?int
    {
        return $this->participationScore;
    }

    public function setParticipationScore(int $participationScore): static
    {
        $this->participationScore = max(0, min(10, $participationScore));
        return $this;
    }

    public function getAbsenceReason(): ?string
    {
        return $this->absenceReason;
    }

    public function setAbsenceReason(?string $absenceReason): static
    {
        $this->absenceReason = $absenceReason;
        return $this;
    }

    public function isExcused(): ?bool
    {
        return $this->excused;
    }

    public function setExcused(bool $excused): static
    {
        $this->excused = $excused;
        return $this;
    }

    public function getAdminNotes(): ?string
    {
        return $this->adminNotes;
    }

    public function setAdminNotes(?string $adminNotes): static
    {
        $this->adminNotes = $adminNotes;
        return $this;
    }

    public function getArrivalTime(): ?\DateTimeInterface
    {
        return $this->arrivalTime;
    }

    public function setArrivalTime(?\DateTimeInterface $arrivalTime): static
    {
        $this->arrivalTime = $arrivalTime;
        $this->calculateLateness();
        return $this;
    }

    public function getDepartureTime(): ?\DateTimeInterface
    {
        return $this->departureTime;
    }

    public function setDepartureTime(?\DateTimeInterface $departureTime): static
    {
        $this->departureTime = $departureTime;
        $this->calculateEarlyDeparture();
        return $this;
    }

    public function getMinutesLate(): ?int
    {
        return $this->minutesLate;
    }

    public function setMinutesLate(?int $minutesLate): static
    {
        $this->minutesLate = $minutesLate;
        return $this;
    }

    public function getMinutesEarlyDeparture(): ?int
    {
        return $this->minutesEarlyDeparture;
    }

    public function setMinutesEarlyDeparture(?int $minutesEarlyDeparture): static
    {
        $this->minutesEarlyDeparture = $minutesEarlyDeparture;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata ?? [];
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getRecordedAt(): ?\DateTimeInterface
    {
        return $this->recordedAt;
    }

    public function setRecordedAt(\DateTimeInterface $recordedAt): static
    {
        $this->recordedAt = $recordedAt;
        return $this;
    }

    public function getRecordedBy(): ?string
    {
        return $this->recordedBy;
    }

    public function setRecordedBy(?string $recordedBy): static
    {
        $this->recordedBy = $recordedBy;
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
     * Check if the student was present
     */
    public function isPresent(): bool
    {
        return in_array($this->status, [self::STATUS_PRESENT, self::STATUS_LATE, self::STATUS_PARTIAL]);
    }

    /**
     * Check if the student was absent
     */
    public function isAbsent(): bool
    {
        return $this->status === self::STATUS_ABSENT;
    }

    /**
     * Check if the student was late
     */
    public function isLate(): bool
    {
        return $this->status === self::STATUS_LATE;
    }

    /**
     * Check if the student had partial attendance
     */
    public function isPartial(): bool
    {
        return $this->status === self::STATUS_PARTIAL;
    }

    /**
     * Get status label for display
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PRESENT => 'Présent',
            self::STATUS_ABSENT => 'Absent',
            self::STATUS_LATE => 'En retard',
            self::STATUS_PARTIAL => 'Présence partielle',
            default => 'Inconnu'
        };
    }

    /**
     * Get status badge class for display
     */
    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_PRESENT => 'bg-success',
            self::STATUS_ABSENT => 'bg-danger',
            self::STATUS_LATE => 'bg-warning',
            self::STATUS_PARTIAL => 'bg-info',
            default => 'bg-secondary'
        };
    }

    /**
     * Get participation score badge class
     */
    public function getParticipationBadgeClass(): string
    {
        if ($this->participationScore >= 8) {
            return 'bg-success';
        } elseif ($this->participationScore >= 6) {
            return 'bg-warning';
        } elseif ($this->participationScore >= 4) {
            return 'bg-info';
        } else {
            return 'bg-danger';
        }
    }

    /**
     * Calculate lateness in minutes
     */
    private function calculateLateness(): void
    {
        if (!$this->session || !$this->arrivalTime) {
            $this->minutesLate = null;
            return;
        }

        $sessionStart = $this->session->getStartDate();
        if ($sessionStart && $this->arrivalTime > $sessionStart) {
            $diff = $sessionStart->diff($this->arrivalTime);
            $this->minutesLate = ($diff->h * 60) + $diff->i;
            
            // Automatically set status to late if more than 5 minutes
            if ($this->minutesLate > 5 && $this->status === self::STATUS_PRESENT) {
                $this->status = self::STATUS_LATE;
            }
        } else {
            $this->minutesLate = 0;
        }
    }

    /**
     * Calculate early departure in minutes
     */
    private function calculateEarlyDeparture(): void
    {
        if (!$this->session || !$this->departureTime) {
            $this->minutesEarlyDeparture = null;
            return;
        }

        $sessionEnd = $this->session->getEndDate();
        if ($sessionEnd && $this->departureTime < $sessionEnd) {
            $diff = $this->departureTime->diff($sessionEnd);
            $this->minutesEarlyDeparture = ($diff->h * 60) + $diff->i;
            
            // Automatically set status to partial if left early
            if ($this->minutesEarlyDeparture > 15 && $this->status === self::STATUS_PRESENT) {
                $this->status = self::STATUS_PARTIAL;
            }
        } else {
            $this->minutesEarlyDeparture = 0;
        }
    }

    /**
     * Mark as present
     */
    public function markPresent(): static
    {
        $this->status = self::STATUS_PRESENT;
        $this->arrivalTime = null;
        $this->departureTime = null;
        $this->minutesLate = null;
        $this->minutesEarlyDeparture = null;
        $this->absenceReason = null;
        return $this;
    }

    /**
     * Mark as absent with reason
     */
    public function markAbsent(?string $reason = null, bool $excused = false): static
    {
        $this->status = self::STATUS_ABSENT;
        $this->absenceReason = $reason;
        $this->excused = $excused;
        $this->participationScore = 0;
        $this->arrivalTime = null;
        $this->departureTime = null;
        return $this;
    }

    /**
     * Mark as late with arrival time
     */
    public function markLate(\DateTimeInterface $arrivalTime): static
    {
        $this->status = self::STATUS_LATE;
        $this->setArrivalTime($arrivalTime);
        return $this;
    }

    /**
     * Mark as partial attendance
     */
    public function markPartial(?\DateTimeInterface $departureTime = null): static
    {
        $this->status = self::STATUS_PARTIAL;
        if ($departureTime) {
            $this->setDepartureTime($departureTime);
        }
        return $this;
    }

    /**
     * Add metadata
     */
    public function addMetadata(string $key, mixed $value): static
    {
        $metadata = $this->getMetadata();
        $metadata[$key] = $value;
        $this->setMetadata($metadata);
        return $this;
    }

    /**
     * Get metadata value
     */
    public function getMetadataValue(string $key): mixed
    {
        return $this->getMetadata()[$key] ?? null;
    }

    /**
     * Calculate attendance weight for scoring (0-1)
     */
    public function getAttendanceWeight(): float
    {
        return match ($this->status) {
            self::STATUS_PRESENT => 1.0,
            self::STATUS_LATE => 0.8, // Reduced score for lateness
            self::STATUS_PARTIAL => 0.6, // Partial attendance
            self::STATUS_ABSENT => $this->excused ? 0.3 : 0.0, // Excused absence has some weight
            default => 0.0
        };
    }

    /**
     * Get participation score percentage
     */
    public function getParticipationPercentage(): float
    {
        return ($this->participationScore / 10) * 100;
    }

    /**
     * Get detailed attendance info for reports
     */
    public function getAttendanceInfo(): array
    {
        return [
            'status' => $this->status,
            'statusLabel' => $this->getStatusLabel(),
            'isPresent' => $this->isPresent(),
            'participationScore' => $this->participationScore,
            'participationPercentage' => $this->getParticipationPercentage(),
            'attendanceWeight' => $this->getAttendanceWeight(),
            'minutesLate' => $this->minutesLate,
            'minutesEarlyDeparture' => $this->minutesEarlyDeparture,
            'excused' => $this->excused,
            'absenceReason' => $this->absenceReason,
            'adminNotes' => $this->adminNotes
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
        return sprintf(
            '%s - %s (%s)',
            $this->student?->getFullName() ?? 'Étudiant inconnu',
            $this->session?->getName() ?? 'Session inconnue',
            $this->getStatusLabel()
        );
    }
}
