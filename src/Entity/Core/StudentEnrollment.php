<?php

declare(strict_types=1);

namespace App\Entity\Core;

use App\Entity\Training\SessionRegistration;
use App\Entity\User\Student;
use App\Repository\Core\StudentEnrollmentRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * StudentEnrollment entity linking authenticated students to confirmed session registrations.
 *
 * This entity establishes the foundation for content access control by creating
 * a formal enrollment relationship between Student users and SessionRegistration entries.
 * Essential for the Student Content Access System.
 */
#[ORM\Entity(repositoryClass: StudentEnrollmentRepository::class)]
#[ORM\Table(name: 'student_enrollments')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['student_id', 'session_registration_id'], name: 'idx_student_session_registration')]
#[ORM\Index(columns: ['status'], name: 'idx_enrollment_status')]
#[ORM\Index(columns: ['enrolled_at'], name: 'idx_enrolled_at')]
#[ORM\UniqueConstraint(columns: ['student_id', 'session_registration_id'], name: 'unique_student_session_registration')]
class StudentEnrollment
{
    /**
     * Available enrollment status options.
     */
    public const STATUS_ENROLLED = 'enrolled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DROPPED_OUT = 'dropped_out';
    public const STATUS_SUSPENDED = 'suspended';

    /**
     * Available enrollment statuses.
     */
    public const STATUSES = [
        self::STATUS_ENROLLED => 'Inscrit',
        self::STATUS_COMPLETED => 'Terminé',
        self::STATUS_DROPPED_OUT => 'Abandon',
        self::STATUS_SUSPENDED => 'Suspendu',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Student::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Student $student = null;

    #[ORM\OneToOne(targetEntity: SessionRegistration::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE', unique: true)]
    private ?SessionRegistration $sessionRegistration = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le statut d\'inscription est obligatoire')]
    #[Assert\Choice(
        choices: [self::STATUS_ENROLLED, self::STATUS_COMPLETED, self::STATUS_DROPPED_OUT, self::STATUS_SUSPENDED],
        message: 'Statut d\'inscription invalide'
    )]
    private string $status = self::STATUS_ENROLLED;

    #[ORM\Column]
    private ?DateTimeImmutable $enrolledAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'La raison d\'abandon ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $dropoutReason = null;

    /**
     * Associated StudentProgress for this enrollment (auto-created).
     */
    #[ORM\OneToOne(targetEntity: StudentProgress::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?StudentProgress $progress = null;

    /**
     * Collection of attendance records for this enrollment.
     *
     * @var Collection<int, AttendanceRecord>
     */
    #[ORM\OneToMany(targetEntity: AttendanceRecord::class, mappedBy: 'student', cascade: ['persist'])]
    private Collection $attendanceRecords;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $updatedAt = null;

    /**
     * Additional metadata for enrollment tracking.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    /**
     * Enrollment source information (manual, automatic, import, etc.).
     */
    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(
        max: 50,
        maxMessage: 'La source d\'inscription ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $enrollmentSource = 'manual';

    /**
     * Admin notes about the enrollment.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'Les notes administratives ne peuvent pas dépasser {{ limit }} caractères'
    )]
    private ?string $adminNotes = null;

    public function __construct()
    {
        $this->attendanceRecords = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->enrolledAt = new DateTimeImmutable();
        $this->metadata = [];
    }

    public function __toString(): string
    {
        return sprintf(
            '%s - %s (%s)',
            $this->student?->getFullName() ?? 'Étudiant inconnu',
            $this->sessionRegistration?->getSession()?->getName() ?? 'Session inconnue',
            $this->getStatusLabel()
        );
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

    public function getSessionRegistration(): ?SessionRegistration
    {
        return $this->sessionRegistration;
    }

    public function setSessionRegistration(?SessionRegistration $sessionRegistration): static
    {
        $this->sessionRegistration = $sessionRegistration;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        // Automatically set completion date when status changes to completed
        if ($status === self::STATUS_COMPLETED && $this->completedAt === null) {
            $this->completedAt = new DateTimeImmutable();
        }

        return $this;
    }

    public function getEnrolledAt(): ?DateTimeImmutable
    {
        return $this->enrolledAt;
    }

    public function setEnrolledAt(DateTimeImmutable $enrolledAt): static
    {
        $this->enrolledAt = $enrolledAt;

        return $this;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    public function getDropoutReason(): ?string
    {
        return $this->dropoutReason;
    }

    public function setDropoutReason(?string $dropoutReason): static
    {
        $this->dropoutReason = $dropoutReason;

        return $this;
    }

    public function getProgress(): ?StudentProgress
    {
        return $this->progress;
    }

    public function setProgress(?StudentProgress $progress): static
    {
        $this->progress = $progress;

        return $this;
    }

    /**
     * @return Collection<int, AttendanceRecord>
     */
    public function getAttendanceRecords(): Collection
    {
        return $this->attendanceRecords;
    }

    public function addAttendanceRecord(AttendanceRecord $attendanceRecord): static
    {
        if (!$this->attendanceRecords->contains($attendanceRecord)) {
            $this->attendanceRecords->add($attendanceRecord);
        }

        return $this;
    }

    public function removeAttendanceRecord(AttendanceRecord $attendanceRecord): static
    {
        $this->attendanceRecords->removeElement($attendanceRecord);

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

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

    public function getEnrollmentSource(): ?string
    {
        return $this->enrollmentSource;
    }

    public function setEnrollmentSource(?string $enrollmentSource): static
    {
        $this->enrollmentSource = $enrollmentSource;

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

    /**
     * Get the status label for display.
     */
    public function getStatusLabel(): string
    {
        return self::STATUSES[$this->status] ?? 'Inconnu';
    }

    /**
     * Get the status badge class for display.
     */
    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_ENROLLED => 'bg-success',
            self::STATUS_COMPLETED => 'bg-primary',
            self::STATUS_DROPPED_OUT => 'bg-danger',
            self::STATUS_SUSPENDED => 'bg-warning',
            default => 'bg-secondary'
        };
    }

    /**
     * Check if the enrollment is active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ENROLLED;
    }

    /**
     * Check if the enrollment is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the enrollment was dropped out.
     */
    public function isDroppedOut(): bool
    {
        return $this->status === self::STATUS_DROPPED_OUT;
    }

    /**
     * Check if the enrollment is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    /**
     * Get the formation from the session registration.
     */
    public function getFormation(): ?\App\Entity\Training\Formation
    {
        return $this->sessionRegistration?->getSession()?->getFormation();
    }

    /**
     * Get the session from the session registration.
     */
    public function getSession(): ?\App\Entity\Training\Session
    {
        return $this->sessionRegistration?->getSession();
    }

    /**
     * Mark enrollment as completed.
     */
    public function markCompleted(): static
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completedAt = new DateTimeImmutable();

        return $this;
    }

    /**
     * Mark enrollment as dropped out with reason.
     */
    public function markDroppedOut(string $reason): static
    {
        $this->status = self::STATUS_DROPPED_OUT;
        $this->dropoutReason = $reason;

        return $this;
    }

    /**
     * Mark enrollment as suspended.
     */
    public function markSuspended(): static
    {
        $this->status = self::STATUS_SUSPENDED;

        return $this;
    }

    /**
     * Reactivate enrollment.
     */
    public function reactivate(): static
    {
        $this->status = self::STATUS_ENROLLED;

        return $this;
    }

    /**
     * Get enrollment duration in days.
     */
    public function getEnrollmentDuration(): int
    {
        $endDate = $this->completedAt ?? new DateTimeImmutable();
        
        return $this->enrolledAt->diff($endDate)->days;
    }

    /**
     * Add metadata entry.
     */
    public function addMetadata(string $key, mixed $value): static
    {
        $metadata = $this->getMetadata();
        $metadata[$key] = $value;
        $this->setMetadata($metadata);

        return $this;
    }

    /**
     * Get metadata entry.
     */
    public function getMetadataValue(string $key): mixed
    {
        return $this->getMetadata()[$key] ?? null;
    }

    /**
     * Remove metadata entry.
     */
    public function removeMetadata(string $key): static
    {
        $metadata = $this->getMetadata();
        unset($metadata[$key]);
        $this->setMetadata($metadata);

        return $this;
    }

    /**
     * Get enrollment info for reports.
     */
    public function getEnrollmentInfo(): array
    {
        return [
            'id' => $this->id,
            'student' => $this->student?->getFullName(),
            'studentEmail' => $this->student?->getEmail(),
            'formation' => $this->getFormation()?->getTitle(),
            'session' => $this->getSession()?->getName(),
            'status' => $this->status,
            'statusLabel' => $this->getStatusLabel(),
            'enrolledAt' => $this->enrolledAt?->format('Y-m-d H:i:s'),
            'completedAt' => $this->completedAt?->format('Y-m-d H:i:s'),
            'duration' => $this->getEnrollmentDuration(),
            'isActive' => $this->isActive(),
            'isCompleted' => $this->isCompleted(),
            'dropoutReason' => $this->dropoutReason,
            'enrollmentSource' => $this->enrollmentSource,
            'hasProgress' => $this->progress !== null,
        ];
    }

    /**
     * Lifecycle callback to update the updatedAt timestamp.
     */
    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
