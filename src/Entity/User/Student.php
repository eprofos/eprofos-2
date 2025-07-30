<?php

declare(strict_types=1);

namespace App\Entity\User;

use App\Entity\Alternance\MissionAssignment;
use App\Entity\Core\AttendanceRecord;
use App\Repository\User\StudentRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Student entity for student authentication and management.
 *
 * Represents a student user with authentication capabilities for accessing
 * their personal dashboard, training progress, and educational resources.
 */
#[ORM\Entity(repositoryClass: StudentRepository::class)]
#[ORM\Table(name: 'students')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'Un compte avec cet email existe déjà')]
class Student implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'L\'email est obligatoire')]
    #[Assert\Email(message: 'L\'email n\'est pas valide')]
    private ?string $email = null;

    /**
     * @var list<string> The student roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Le prénom doit contenir au moins 2 caractères')]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Le nom doit contenir au moins 2 caractères')]
    private ?string $lastName = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(max: 20, maxMessage: 'Le numéro de téléphone ne peut pas dépasser 20 caractères')]
    private ?string $phone = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?DateTimeInterface $birthDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'L\'adresse ne peut pas dépasser 255 caractères')]
    private ?string $address = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\Length(max: 10, maxMessage: 'Le code postal ne peut pas dépasser 10 caractères')]
    private ?string $postalCode = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'La ville ne peut pas dépasser 100 caractères')]
    private ?string $city = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'Le pays ne peut pas dépasser 100 caractères')]
    private ?string $country = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'Le niveau d\'études ne peut pas dépasser 100 caractères')]
    private ?string $educationLevel = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'La profession ne peut pas dépasser 100 caractères')]
    private ?string $profession = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'L\'entreprise ne peut pas dépasser 100 caractères')]
    private ?string $company = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column]
    private ?bool $emailVerified = false;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $emailVerificationToken = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $passwordResetToken = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $passwordResetTokenExpiresAt = null;

    /**
     * Collection of mission assignments for this student.
     *
     * @var Collection<int, MissionAssignment>
     */
    #[ORM\OneToMany(mappedBy: 'student', targetEntity: MissionAssignment::class, cascade: ['persist', 'remove'])]
    private Collection $missionAssignments;

    /**
     * Collection of attendance records for this student.
     *
     * @var Collection<int, AttendanceRecord>
     */
    #[ORM\OneToMany(mappedBy: 'student', targetEntity: AttendanceRecord::class, cascade: ['persist'])]
    private Collection $attendanceRecords;

    public function __construct()
    {
        $this->missionAssignments = new ArrayCollection();
        $this->attendanceRecords = new ArrayCollection();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->roles = ['ROLE_STUDENT'];
    }

    public function __toString(): string
    {
        return $this->getFullName() ?: $this->email ?: '';
    }

    public function getId(): ?int
    {
        return $this->id;
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

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_STUDENT
        $roles[] = 'ROLE_STUDENT';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
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

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getBirthDate(): ?DateTimeInterface
    {
        return $this->birthDate;
    }

    public function setBirthDate(?DateTimeInterface $birthDate): static
    {
        $this->birthDate = $birthDate;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getEducationLevel(): ?string
    {
        return $this->educationLevel;
    }

    public function setEducationLevel(?string $educationLevel): static
    {
        $this->educationLevel = $educationLevel;

        return $this;
    }

    public function getProfession(): ?string
    {
        return $this->profession;
    }

    public function setProfession(?string $profession): static
    {
        $this->profession = $profession;

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

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function isEmailVerified(): ?bool
    {
        return $this->emailVerified;
    }

    public function setEmailVerified(bool $emailVerified): static
    {
        $this->emailVerified = $emailVerified;

        return $this;
    }

    public function getEmailVerificationToken(): ?string
    {
        return $this->emailVerificationToken;
    }

    public function setEmailVerificationToken(?string $emailVerificationToken): static
    {
        $this->emailVerificationToken = $emailVerificationToken;

        return $this;
    }

    public function getEmailVerifiedAt(): ?DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function setEmailVerifiedAt(?DateTimeImmutable $emailVerifiedAt): static
    {
        $this->emailVerifiedAt = $emailVerifiedAt;

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

    public function getLastLoginAt(): ?DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?DateTimeImmutable $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    public function getPasswordResetToken(): ?string
    {
        return $this->passwordResetToken;
    }

    public function setPasswordResetToken(?string $passwordResetToken): static
    {
        $this->passwordResetToken = $passwordResetToken;

        return $this;
    }

    public function getPasswordResetTokenExpiresAt(): ?DateTimeImmutable
    {
        return $this->passwordResetTokenExpiresAt;
    }

    public function setPasswordResetTokenExpiresAt(?DateTimeImmutable $passwordResetTokenExpiresAt): static
    {
        $this->passwordResetTokenExpiresAt = $passwordResetTokenExpiresAt;

        return $this;
    }

    /**
     * Get the full name of the student.
     */
    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    /**
     * Get the initials of the student for avatar display.
     */
    public function getInitials(): string
    {
        $firstInitial = $this->firstName ? strtoupper(substr($this->firstName, 0, 1)) : '';
        $lastInitial = $this->lastName ? strtoupper(substr($this->lastName, 0, 1)) : '';

        return $firstInitial . $lastInitial;
    }

    /**
     * Update the last login timestamp.
     */
    public function updateLastLogin(): void
    {
        $this->lastLoginAt = new DateTimeImmutable();
    }

    /**
     * Generate email verification token.
     */
    public function generateEmailVerificationToken(): string
    {
        $this->emailVerificationToken = bin2hex(random_bytes(32));

        return $this->emailVerificationToken;
    }

    /**
     * Verify email address.
     */
    public function verifyEmail(): void
    {
        $this->emailVerified = true;
        $this->emailVerificationToken = null;
        $this->emailVerifiedAt = new DateTimeImmutable();
    }

    /**
     * Generate password reset token.
     */
    public function generatePasswordResetToken(): string
    {
        $this->passwordResetToken = bin2hex(random_bytes(32));
        $this->passwordResetTokenExpiresAt = new DateTimeImmutable('+1 hour');

        return $this->passwordResetToken;
    }

    /**
     * Clear password reset token.
     */
    public function clearPasswordResetToken(): void
    {
        $this->passwordResetToken = null;
        $this->passwordResetTokenExpiresAt = null;
    }

    /**
     * Check if password reset token is valid.
     */
    public function isPasswordResetTokenValid(): bool
    {
        return $this->passwordResetToken !== null
            && $this->passwordResetTokenExpiresAt !== null
            && $this->passwordResetTokenExpiresAt > new DateTimeImmutable();
    }

    /**
     * Get the complete address as a string.
     */
    public function getCompleteAddress(): string
    {
        $addressParts = array_filter([
            $this->address,
            $this->postalCode,
            $this->city,
            $this->country,
        ]);

        return implode(', ', $addressParts);
    }

    /**
     * Lifecycle callback to update the updatedAt timestamp.
     */
    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * @return Collection<int, MissionAssignment>
     */
    public function getMissionAssignments(): Collection
    {
        return $this->missionAssignments;
    }

    public function addMissionAssignment(MissionAssignment $missionAssignment): static
    {
        if (!$this->missionAssignments->contains($missionAssignment)) {
            $this->missionAssignments->add($missionAssignment);
            $missionAssignment->setStudent($this);
        }

        return $this;
    }

    public function removeMissionAssignment(MissionAssignment $missionAssignment): static
    {
        if ($this->missionAssignments->removeElement($missionAssignment)) {
            // set the owning side to null (unless already changed)
            if ($missionAssignment->getStudent() === $this) {
                $missionAssignment->setStudent(null);
            }
        }

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
            $attendanceRecord->setStudent($this);
        }

        return $this;
    }

    public function removeAttendanceRecord(AttendanceRecord $attendanceRecord): static
    {
        if ($this->attendanceRecords->removeElement($attendanceRecord)) {
            // set the owning side to null (unless already changed)
            if ($attendanceRecord->getStudent() === $this) {
                $attendanceRecord->setStudent(null);
            }
        }

        return $this;
    }
}
