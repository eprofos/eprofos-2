<?php

declare(strict_types=1);

namespace App\Entity\Student;

use App\Entity\Core\StudentEnrollment;
use App\Entity\Training\Formation;
use App\Entity\User\Student;
use App\Repository\Student\CertificateRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Certificate entity for managing completion certificates for students.
 *
 * Generates and manages completion certificates for students who successfully
 * finish formations, with Qualiopi-compliant documentation and automated delivery.
 */
#[ORM\Entity(repositoryClass: CertificateRepository::class)]
#[ORM\Table(name: 'student_certificates')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['student_id'], name: 'idx_certificate_student')]
#[ORM\Index(columns: ['formation_id'], name: 'idx_certificate_formation')]
#[ORM\Index(columns: ['certificate_number'], name: 'idx_certificate_number')]
#[ORM\Index(columns: ['verification_code'], name: 'idx_verification_code')]
#[ORM\Index(columns: ['status'], name: 'idx_certificate_status')]
#[ORM\Index(columns: ['issued_at'], name: 'idx_issued_at')]
#[ORM\UniqueConstraint(columns: ['student_id', 'formation_id'], name: 'unique_student_formation_certificate')]
class Certificate
{
    /**
     * Certificate status constants.
     */
    public const STATUS_ISSUED = 'issued';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_REISSUED = 'reissued';

    /**
     * Available certificate statuses.
     */
    public const STATUSES = [
        self::STATUS_ISSUED => 'Émis',
        self::STATUS_REVOKED => 'Révoqué',
        self::STATUS_REISSUED => 'Réémis',
    ];

    /**
     * Certificate grades for different performance levels.
     */
    public const GRADE_A = 'A';

    public const GRADE_B = 'B';

    public const GRADE_C = 'C';

    public const GRADE_D = 'D';

    public const GRADE_F = 'F';

    /**
     * Grade thresholds.
     */
    public const GRADE_THRESHOLDS = [
        self::GRADE_A => 90,  // Excellence
        self::GRADE_B => 80,  // Très bien
        self::GRADE_C => 70,  // Bien
        self::GRADE_D => 60,  // Satisfaisant
        self::GRADE_F => 0,   // Insuffisant (no certificate)
    ];

    /**
     * Grade labels.
     */
    public const GRADE_LABELS = [
        self::GRADE_A => 'Excellence',
        self::GRADE_B => 'Très bien',
        self::GRADE_C => 'Bien',
        self::GRADE_D => 'Satisfaisant',
        self::GRADE_F => 'Insuffisant',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Student::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Student $student = null;

    #[ORM\ManyToOne(targetEntity: Formation::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Formation $formation = null;

    #[ORM\OneToOne(targetEntity: StudentEnrollment::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?StudentEnrollment $enrollment = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $certificateNumber = null;

    #[ORM\Column(length: 64, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    private ?string $verificationCode = null;

    #[ORM\Column]
    private ?DateTimeImmutable $issuedAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $revokedAt = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: [self::STATUS_ISSUED, self::STATUS_REVOKED, self::STATUS_REISSUED])]
    private string $status = self::STATUS_ISSUED;

    #[ORM\Column(type: Types::JSON)]
    private array $completionData = [];

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    private ?string $certificateTemplate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pdfPath = null;

    #[ORM\Column(type: Types::JSON)]
    private array $metadata = [];

    #[ORM\Column(length: 2)]
    #[Assert\Choice(choices: [self::GRADE_A, self::GRADE_B, self::GRADE_C, self::GRADE_D, self::GRADE_F])]
    private ?string $grade = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    #[Assert\Range(min: 0, max: 100)]
    private ?string $finalScore = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $revocationReason = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->generateCertificateNumber();
        $this->generateVerificationCode();
        $this->issuedAt = new DateTimeImmutable();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * String representation for debugging.
     */
    public function __toString(): string
    {
        return sprintf(
            'Certificate %s for %s - %s (%s)',
            $this->certificateNumber ?? 'N/A',
            $this->student?->getFullName() ?? 'Unknown Student',
            $this->formation?->getTitle() ?? 'Unknown Formation',
            $this->getStatusLabel(),
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

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): static
    {
        $this->formation = $formation;

        return $this;
    }

    public function getEnrollment(): ?StudentEnrollment
    {
        return $this->enrollment;
    }

    public function setEnrollment(?StudentEnrollment $enrollment): static
    {
        $this->enrollment = $enrollment;

        return $this;
    }

    public function getCertificateNumber(): ?string
    {
        return $this->certificateNumber;
    }

    public function setCertificateNumber(?string $certificateNumber): static
    {
        $this->certificateNumber = $certificateNumber;

        return $this;
    }

    public function getVerificationCode(): ?string
    {
        return $this->verificationCode;
    }

    public function setVerificationCode(?string $verificationCode): static
    {
        $this->verificationCode = $verificationCode;

        return $this;
    }

    public function getIssuedAt(): ?DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(?DateTimeImmutable $issuedAt): static
    {
        $this->issuedAt = $issuedAt;

        return $this;
    }

    public function getRevokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function setRevokedAt(?DateTimeImmutable $revokedAt): static
    {
        $this->revokedAt = $revokedAt;

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

    public function getCompletionData(): array
    {
        return $this->completionData;
    }

    public function setCompletionData(array $completionData): static
    {
        $this->completionData = $completionData;

        return $this;
    }

    public function getCertificateTemplate(): ?string
    {
        return $this->certificateTemplate;
    }

    public function setCertificateTemplate(?string $certificateTemplate): static
    {
        $this->certificateTemplate = $certificateTemplate;

        return $this;
    }

    public function getPdfPath(): ?string
    {
        return $this->pdfPath;
    }

    public function setPdfPath(?string $pdfPath): static
    {
        $this->pdfPath = $pdfPath;

        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getGrade(): ?string
    {
        return $this->grade;
    }

    public function setGrade(?string $grade): static
    {
        $this->grade = $grade;

        return $this;
    }

    public function getFinalScore(): ?string
    {
        return $this->finalScore;
    }

    public function setFinalScore(?string $finalScore): static
    {
        $this->finalScore = $finalScore;

        return $this;
    }

    public function getRevocationReason(): ?string
    {
        return $this->revocationReason;
    }

    public function setRevocationReason(?string $revocationReason): static
    {
        $this->revocationReason = $revocationReason;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Regenerate verification code (for security purposes).
     */
    public function regenerateVerificationCode(): static
    {
        $this->generateVerificationCode();

        return $this;
    }

    /**
     * Check if certificate is currently valid (not revoked).
     */
    public function isValid(): bool
    {
        return $this->status !== self::STATUS_REVOKED;
    }

    /**
     * Revoke the certificate.
     */
    public function revoke(string $reason): static
    {
        $this->status = self::STATUS_REVOKED;
        $this->revokedAt = new DateTimeImmutable();
        $this->revocationReason = $reason;

        return $this;
    }

    /**
     * Mark certificate as reissued.
     */
    public function markAsReissued(): static
    {
        $this->status = self::STATUS_REISSUED;

        return $this;
    }

    /**
     * Get grade label for display.
     */
    public function getGradeLabel(): ?string
    {
        return $this->grade ? self::GRADE_LABELS[$this->grade] : null;
    }

    /**
     * Calculate grade based on final score.
     */
    public function calculateGrade(): static
    {
        if ($this->finalScore === null) {
            return $this;
        }

        $score = (float) $this->finalScore;

        foreach (self::GRADE_THRESHOLDS as $grade => $threshold) {
            if ($score >= $threshold) {
                $this->grade = $grade;
                break;
            }
        }

        return $this;
    }

    /**
     * Get status label for display.
     */
    public function getStatusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Check if certificate can be downloaded.
     */
    public function canBeDownloaded(): bool
    {
        return $this->isValid() && $this->pdfPath !== null;
    }

    /**
     * Get full PDF file path.
     */
    public function getFullPdfPath(): ?string
    {
        return $this->pdfPath ? '/uploads/certificates/' . $this->pdfPath : null;
    }

    /**
     * Add metadata entry.
     */
    public function addMetadata(string $key, mixed $value): static
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    /**
     * Get metadata value by key.
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Get completion date from completion data.
     */
    public function getCompletionDate(): ?DateTimeImmutable
    {
        $completionDate = $this->completionData['completion_date'] ?? null;

        return $completionDate ? new DateTimeImmutable($completionDate) : null;
    }

    /**
     * Get total hours from completion data.
     */
    public function getTotalHours(): ?int
    {
        return $this->completionData['total_hours'] ?? null;
    }

    /**
     * Get average score from completion data.
     */
    public function getAverageScore(): ?float
    {
        return $this->completionData['average_score'] ?? null;
    }

    /**
     * Get attendance rate from completion data.
     */
    public function getAttendanceRate(): ?float
    {
        return $this->completionData['attendance_rate'] ?? null;
    }

    /**
     * Generate unique certificate number.
     */
    private function generateCertificateNumber(): void
    {
        $this->certificateNumber = 'CERT-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(6)));
    }

    /**
     * Generate unique verification code for QR codes.
     */
    private function generateVerificationCode(): void
    {
        $this->verificationCode = bin2hex(random_bytes(32));
    }
}
