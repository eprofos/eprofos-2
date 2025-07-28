<?php

namespace App\Entity\User;

use App\Repository\MentorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Mentor entity for company mentors/tutors authentication and management
 * 
 * Represents a company mentor (tuteur entreprise) with authentication capabilities 
 * for supervising apprentices and managing apprenticeship missions.
 */
#[ORM\Entity(repositoryClass: MentorRepository::class)]
#[ORM\Table(name: 'mentors')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'Un compte avec cet email existe déjà')]
class Mentor implements UserInterface, PasswordAuthenticatedUserInterface
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
     * @var list<string> The mentor roles
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

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Le poste est obligatoire')]
    #[Assert\Length(min: 2, max: 150, minMessage: 'Le poste doit contenir au moins 2 caractères')]
    private ?string $position = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank(message: 'Le nom de l\'entreprise est obligatoire')]
    #[Assert\Length(min: 2, max: 200, minMessage: 'Le nom de l\'entreprise doit contenir au moins 2 caractères')]
    private ?string $companyName = null;

    #[ORM\Column(length: 14)]
    #[Assert\NotBlank(message: 'Le SIRET est obligatoire')]
    #[Assert\Length(min: 14, max: 14, minMessage: 'Le SIRET doit contenir exactement 14 chiffres', maxMessage: 'Le SIRET doit contenir exactement 14 chiffres')]
    #[Assert\Regex(pattern: '/^\d{14}$/', message: 'Le SIRET doit contenir uniquement des chiffres')]
    private ?string $companySiret = null;

    #[ORM\Column(type: 'json')]
    #[Assert\NotNull(message: 'Les domaines d\'expertise sont obligatoires')]
    #[Assert\Count(min: 1, minMessage: 'Au moins un domaine d\'expertise doit être sélectionné')]
    private array $expertiseDomains = [];

    #[ORM\Column]
    #[Assert\NotNull(message: 'L\'expérience est obligatoire')]
    #[Assert\PositiveOrZero(message: 'L\'expérience doit être un nombre positif')]
    #[Assert\LessThan(60, message: 'L\'expérience ne peut pas dépasser 60 ans')]
    private ?int $experienceYears = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le niveau de formation est obligatoire')]
    #[Assert\Choice(
        choices: ['bac', 'bac+2', 'bac+3', 'bac+5', 'bac+8'],
        message: 'Niveau de formation invalide'
    )]
    private ?string $educationLevel = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column]
    private ?bool $emailVerified = false;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $emailVerificationToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $passwordResetToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $passwordResetTokenExpiresAt = null;

    // Relationships - These will be implemented when Alternant and Mission entities are created
    // For now, we'll prepare the structure with placeholder comments

    /**
     * Collection of apprentices (alternants) supervised by this mentor
     * 
     * @var Collection<int, Alternant>
     */
    // #[ORM\OneToMany(mappedBy: 'mentor', targetEntity: Alternant::class)]
    // private Collection $alternants;

    /**
     * Collection of missions created by this mentor
     * 
     * @var Collection<int, Mission>
     */
    // #[ORM\OneToMany(mappedBy: 'mentor', targetEntity: Mission::class)]
    // private Collection $missions;

    /**
     * Available expertise domains for mentors
     */
    public const EXPERTISE_DOMAINS = [
        'informatique' => 'Informatique & Numérique',
        'gestion' => 'Gestion & Administration',
        'commercial' => 'Commercial & Vente',
        'marketing' => 'Marketing & Communication',
        'rh' => 'Ressources Humaines',
        'finance' => 'Finance & Comptabilité',
        'logistique' => 'Logistique & Supply Chain',
        'production' => 'Production & Qualité',
        'juridique' => 'Juridique & Compliance',
        'technique' => 'Technique & Ingénierie',
        'management' => 'Management & Leadership',
        'formation' => 'Formation & Pédagogie',
        'autre' => 'Autre domaine'
    ];

    /**
     * Available education levels for mentors
     */
    public const EDUCATION_LEVELS = [
        'bac' => 'Baccalauréat',
        'bac+2' => 'Bac+2 (BTS, DUT, etc.)',
        'bac+3' => 'Bac+3 (Licence, Bachelor)',
        'bac+5' => 'Bac+5 (Master, Ingénieur)',
        'bac+8' => 'Bac+8 (Doctorat, PhD)'
    ];

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->roles = ['ROLE_MENTOR'];
        // $this->alternants = new ArrayCollection();
        // $this->missions = new ArrayCollection();
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
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_MENTOR
        $roles[] = 'ROLE_MENTOR';

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

    public function getPosition(): ?string
    {
        return $this->position;
    }

    public function setPosition(string $position): static
    {
        $this->position = $position;
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

    public function getCompanySiret(): ?string
    {
        return $this->companySiret;
    }

    public function setCompanySiret(string $companySiret): static
    {
        $this->companySiret = $companySiret;
        return $this;
    }

    public function getExpertiseDomains(): array
    {
        return $this->expertiseDomains;
    }

    public function setExpertiseDomains(array $expertiseDomains): static
    {
        $this->expertiseDomains = $expertiseDomains;
        return $this;
    }

    public function getExperienceYears(): ?int
    {
        return $this->experienceYears;
    }

    public function setExperienceYears(int $experienceYears): static
    {
        $this->experienceYears = $experienceYears;
        return $this;
    }

    public function getEducationLevel(): ?string
    {
        return $this->educationLevel;
    }

    public function setEducationLevel(string $educationLevel): static
    {
        $this->educationLevel = $educationLevel;
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

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function setEmailVerifiedAt(?\DateTimeImmutable $emailVerifiedAt): static
    {
        $this->emailVerifiedAt = $emailVerifiedAt;
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

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): static
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

    public function getPasswordResetTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->passwordResetTokenExpiresAt;
    }

    public function setPasswordResetTokenExpiresAt(?\DateTimeImmutable $passwordResetTokenExpiresAt): static
    {
        $this->passwordResetTokenExpiresAt = $passwordResetTokenExpiresAt;
        return $this;
    }

    /**
     * Get the full name of the mentor
     */
    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    /**
     * Get the display name with company and position
     */
    public function getDisplayName(): string
    {
        $name = $this->getFullName();
        if ($this->position && $this->companyName) {
            return $name . ' - ' . $this->position . ' chez ' . $this->companyName;
        }
        if ($this->companyName) {
            return $name . ' - ' . $this->companyName;
        }
        return $name;
    }

    /**
     * Get the initials of the mentor for avatar display
     */
    public function getInitials(): string
    {
        $firstInitial = $this->firstName ? strtoupper(substr($this->firstName, 0, 1)) : '';
        $lastInitial = $this->lastName ? strtoupper(substr($this->lastName, 0, 1)) : '';
        
        return $firstInitial . $lastInitial;
    }

    /**
     * Get expertise domains as human-readable labels
     */
    public function getExpertiseDomainsLabels(): array
    {
        $labels = [];
        foreach ($this->expertiseDomains as $domain) {
            $labels[] = self::EXPERTISE_DOMAINS[$domain] ?? $domain;
        }
        return $labels;
    }

    /**
     * Get education level as human-readable label
     */
    public function getEducationLevelLabel(): string
    {
        return self::EDUCATION_LEVELS[$this->educationLevel] ?? $this->educationLevel;
    }

    /**
     * Get experience description
     */
    public function getExperienceDescription(): string
    {
        if ($this->experienceYears === null) {
            return 'Expérience non renseignée';
        }

        if ($this->experienceYears < 1) {
            return 'Moins d\'un an d\'expérience';
        }

        if ($this->experienceYears === 1) {
            return '1 an d\'expérience';
        }

        return $this->experienceYears . ' ans d\'expérience';
    }

    /**
     * Update the last login timestamp
     */
    public function updateLastLogin(): void
    {
        $this->lastLoginAt = new \DateTimeImmutable();
    }

    /**
     * Generate email verification token
     */
    public function generateEmailVerificationToken(): string
    {
        $this->emailVerificationToken = bin2hex(random_bytes(32));
        return $this->emailVerificationToken;
    }

    /**
     * Verify email address
     */
    public function verifyEmail(): void
    {
        $this->emailVerified = true;
        $this->emailVerificationToken = null;
        $this->emailVerifiedAt = new \DateTimeImmutable();
    }

    /**
     * Generate password reset token
     */
    public function generatePasswordResetToken(): string
    {
        $this->passwordResetToken = bin2hex(random_bytes(32));
        $this->passwordResetTokenExpiresAt = new \DateTimeImmutable('+1 hour');
        return $this->passwordResetToken;
    }

    /**
     * Clear password reset token
     */
    public function clearPasswordResetToken(): void
    {
        $this->passwordResetToken = null;
        $this->passwordResetTokenExpiresAt = null;
    }

    /**
     * Check if password reset token is valid
     */
    public function isPasswordResetTokenValid(): bool
    {
        return $this->passwordResetToken !== null 
            && $this->passwordResetTokenExpiresAt !== null 
            && $this->passwordResetTokenExpiresAt > new \DateTimeImmutable();
    }

    /**
     * Get company information as a formatted string
     */
    public function getCompanyInfo(): string
    {
        return $this->companyName . ' (SIRET: ' . $this->companySiret . ')';
    }

    /**
     * Check if mentor has a specific expertise domain
     */
    public function hasExpertiseDomain(string $domain): bool
    {
        return in_array($domain, $this->expertiseDomains);
    }

    /**
     * Get number of supervised apprentices
     * This will be implemented when Alternant entity is created
     */
    public function getAlternantsCount(): int
    {
        // return $this->alternants->count();
        return 0; // Placeholder until Alternant entity is implemented
    }

    /**
     * Get number of created missions
     * This will be implemented when Mission entity is created
     */
    public function getMissionsCount(): int
    {
        // return $this->missions->count();
        return 0; // Placeholder until Mission entity is implemented
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
        return $this->getDisplayName() ?: $this->email ?: '';
    }
}
