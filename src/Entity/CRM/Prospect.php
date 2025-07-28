<?php

declare(strict_types=1);

namespace App\Entity\CRM;

use App\Entity\Analysis\NeedsAnalysisRequest;
use App\Entity\Service\Service;
use App\Entity\Training\Formation;
use App\Entity\Training\SessionRegistration;
use App\Entity\User\Admin;
use App\Repository\CRM\ProspectRepository;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Prospect Entity.
 *
 * Represents a potential customer in the EPROFOS prospect management system.
 * Tracks leads, their information, and progression through the sales funnel.
 */
#[ORM\Entity(repositoryClass: ProspectRepository::class)]
#[ORM\Table(name: 'prospects')]
#[ORM\HasLifecycleCallbacks]
class Prospect
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.',
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-ZÀ-ÿ\s\-\']+$/',
        message: 'Le prénom ne peut contenir que des lettres, espaces, tirets et apostrophes.',
    )]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.',
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-ZÀ-ÿ\s\-\']+$/',
        message: 'Le nom ne peut contenir que des lettres, espaces, tirets et apostrophes.',
    )]
    private ?string $lastName = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
    #[Assert\Email(message: 'Veuillez saisir une adresse email valide.')]
    #[Assert\Length(
        max: 180,
        maxMessage: 'L\'email ne peut pas dépasser {{ limit }} caractères.',
    )]
    private ?string $email = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex(
        pattern: '/^(?:\+33|0)[1-9](?:[0-9]{8})$/',
        message: 'Veuillez saisir un numéro de téléphone français valide.',
    )]
    private ?string $phone = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(
        max: 150,
        maxMessage: 'Le nom de l\'entreprise ne peut pas dépasser {{ limit }} caractères.',
    )]
    private ?string $company = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(
        max: 100,
        maxMessage: 'Le poste ne peut pas dépasser {{ limit }} caractères.',
    )]
    private ?string $position = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: ['lead', 'prospect', 'qualified', 'negotiation', 'customer', 'lost'],
        message: 'Statut invalide.',
    )]
    private string $status = 'lead';

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'La priorité est obligatoire.')]
    #[Assert\Choice(
        choices: ['low', 'medium', 'high', 'urgent'],
        message: 'Priorité invalide.',
    )]
    private string $priority = 'medium';

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Choice(
        choices: ['website', 'referral', 'social_media', 'email_campaign', 'phone_call', 'event', 'advertising', 'other'],
        message: 'Source invalide.',
    )]
    private ?string $source = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.',
    )]
    private ?string $description = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    #[Assert\PositiveOrZero(message: 'Le budget estimé doit être positif ou zéro.')]
    private ?float $estimatedBudget = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?DateTimeInterface $expectedClosureDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $lastContactDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $nextFollowUpDate = null;

    #[ORM\ManyToOne(targetEntity: Admin::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Admin $assignedTo = null;

    #[ORM\ManyToMany(targetEntity: Formation::class)]
    #[ORM\JoinTable(name: 'prospect_formations')]
    private Collection $interestedFormations;

    #[ORM\ManyToMany(targetEntity: Service::class)]
    #[ORM\JoinTable(name: 'prospect_services')]
    private Collection $interestedServices;

    #[ORM\OneToMany(mappedBy: 'prospect', targetEntity: ProspectNote::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $notes;

    /**
     * @var Collection<int, SessionRegistration>
     */
    #[ORM\OneToMany(targetEntity: SessionRegistration::class, mappedBy: 'prospect')]
    private Collection $sessionRegistrations;

    /**
     * @var Collection<int, ContactRequest>
     */
    #[ORM\OneToMany(targetEntity: ContactRequest::class, mappedBy: 'prospect')]
    private Collection $contactRequests;

    /**
     * @var Collection<int, NeedsAnalysisRequest>
     */
    #[ORM\OneToMany(targetEntity: NeedsAnalysisRequest::class, mappedBy: 'prospect')]
    private Collection $needsAnalysisRequests;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $customFields = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $tags = null;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->createdAt = new DateTime();
        $this->interestedFormations = new ArrayCollection();
        $this->interestedServices = new ArrayCollection();
        $this->notes = new ArrayCollection();
        $this->sessionRegistrations = new ArrayCollection();
        $this->contactRequests = new ArrayCollection();
        $this->needsAnalysisRequests = new ArrayCollection();
        $this->tags = [];
    }

    public function __toString(): string
    {
        return $this->getFullName();
    }

    /**
     * Lifecycle callback executed before persisting the entity.
     */
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();
    }

    /**
     * Lifecycle callback executed before updating the entity.
     */
    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTime();
    }

    /**
     * Get the full name of the prospect.
     */
    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    /**
     * Get the status label for display.
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'lead' => 'Lead',
            'prospect' => 'Prospect',
            'qualified' => 'Qualifié',
            'negotiation' => 'Négociation',
            'customer' => 'Client',
            'lost' => 'Perdu',
            default => 'Inconnu'
        };
    }

    /**
     * Get the status badge class for display.
     */
    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'lead' => 'bg-secondary',
            'prospect' => 'bg-info',
            'qualified' => 'bg-warning',
            'negotiation' => 'bg-primary',
            'customer' => 'bg-success',
            'lost' => 'bg-danger',
            default => 'bg-dark'
        };
    }

    /**
     * Get the priority label for display.
     */
    public function getPriorityLabel(): string
    {
        return match ($this->priority) {
            'low' => 'Faible',
            'medium' => 'Moyenne',
            'high' => 'Élevée',
            'urgent' => 'Urgente',
            default => 'Moyenne'
        };
    }

    /**
     * Get the priority badge class for display.
     */
    public function getPriorityBadgeClass(): string
    {
        return match ($this->priority) {
            'low' => 'bg-light text-dark',
            'medium' => 'bg-info',
            'high' => 'bg-warning',
            'urgent' => 'bg-danger',
            default => 'bg-info'
        };
    }

    /**
     * Get the source label for display.
     */
    public function getSourceLabel(): string
    {
        return match ($this->source) {
            'website' => 'Site web',
            'referral' => 'Recommandation',
            'social_media' => 'Réseaux sociaux',
            'email_campaign' => 'Campagne email',
            'phone_call' => 'Appel téléphonique',
            'event' => 'Événement',
            'advertising' => 'Publicité',
            'other' => 'Autre',
            default => 'Non défini'
        };
    }

    /**
     * Check if the prospect needs follow-up.
     */
    public function needsFollowUp(): bool
    {
        if (!$this->nextFollowUpDate) {
            return false;
        }

        return $this->nextFollowUpDate <= new DateTime();
    }

    /**
     * Check if the prospect is overdue for follow-up.
     */
    public function isOverdueForFollowUp(): bool
    {
        if (!$this->nextFollowUpDate) {
            return false;
        }

        $now = new DateTime();
        $overdueDays = 3; // Consider overdue after 3 days

        return $this->nextFollowUpDate->diff($now)->days > $overdueDays && $this->nextFollowUpDate < $now;
    }

    /**
     * Get days since last contact.
     */
    public function getDaysSinceLastContact(): ?int
    {
        if (!$this->lastContactDate) {
            return null;
        }

        $now = new DateTime();

        return $this->lastContactDate->diff($now)->days;
    }

    /**
     * Get days until next follow-up.
     */
    public function getDaysUntilFollowUp(): ?int
    {
        if (!$this->nextFollowUpDate) {
            return null;
        }

        $now = new DateTime();
        $diff = $now->diff($this->nextFollowUpDate);

        return $this->nextFollowUpDate < $now ? -$diff->days : $diff->days;
    }

    /**
     * Add a note to the prospect.
     */
    public function addNote(ProspectNote $note): static
    {
        if (!$this->notes->contains($note)) {
            $this->notes->add($note);
            $note->setProspect($this);
        }

        return $this;
    }

    /**
     * Remove a note from the prospect.
     */
    public function removeNote(ProspectNote $note): static
    {
        if ($this->notes->removeElement($note)) {
            if ($note->getProspect() === $this) {
                $note->setProspect(null);
            }
        }

        return $this;
    }

    /**
     * Add an interested formation.
     */
    public function addInterestedFormation(Formation $formation): static
    {
        if (!$this->interestedFormations->contains($formation)) {
            $this->interestedFormations->add($formation);
        }

        return $this;
    }

    /**
     * Remove an interested formation.
     */
    public function removeInterestedFormation(Formation $formation): static
    {
        $this->interestedFormations->removeElement($formation);

        return $this;
    }

    /**
     * Add an interested service.
     */
    public function addInterestedService(Service $service): static
    {
        if (!$this->interestedServices->contains($service)) {
            $this->interestedServices->add($service);
        }

        return $this;
    }

    /**
     * Remove an interested service.
     */
    public function removeInterestedService(Service $service): static
    {
        $this->interestedServices->removeElement($service);

        return $this;
    }

    /**
     * Add a tag.
     */
    public function addTag(string $tag): static
    {
        $tags = $this->tags ?? [];
        if (!in_array($tag, $tags, true)) {
            $tags[] = $tag;
            $this->tags = $tags;
        }

        return $this;
    }

    /**
     * Remove a tag.
     */
    public function removeTag(string $tag): static
    {
        $tags = $this->tags ?? [];
        $this->tags = array_values(array_filter($tags, static fn ($t) => $t !== $tag));

        return $this;
    }

    /**
     * Check if prospect has a specific tag.
     */
    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags ?? [], true);
    }

    /**
     * @return Collection<int, SessionRegistration>
     */
    public function getSessionRegistrations(): Collection
    {
        return $this->sessionRegistrations;
    }

    /**
     * @return Collection<int, ContactRequest>
     */
    public function getContactRequests(): Collection
    {
        return $this->contactRequests;
    }

    /**
     * @return Collection<int, NeedsAnalysisRequest>
     */
    public function getNeedsAnalysisRequests(): Collection
    {
        return $this->needsAnalysisRequests;
    }

    /**
     * Get all interactions (registrations, contacts, needs analysis) for timeline.
     */
    public function getAllInteractions(): array
    {
        $interactions = [];

        foreach ($this->sessionRegistrations as $registration) {
            $interactions[] = [
                'type' => 'session_registration',
                'entity' => $registration,
                'date' => $registration->getCreatedAt(),
                'title' => 'Inscription session: ' . $registration->getSession()->getName(),
                'description' => 'Formation: ' . $registration->getSession()->getFormation()->getTitle(),
            ];
        }

        foreach ($this->contactRequests as $contact) {
            $interactions[] = [
                'type' => 'contact_request',
                'entity' => $contact,
                'date' => $contact->getCreatedAt(),
                'title' => $contact->getTypeLabel(),
                'description' => $contact->getSubject() ?: substr($contact->getMessage(), 0, 100) . '...',
            ];
        }

        foreach ($this->needsAnalysisRequests as $analysis) {
            $interactions[] = [
                'type' => 'needs_analysis',
                'entity' => $analysis,
                'date' => $analysis->getCreatedAt(),
                'title' => 'Analyse de besoins: ' . $analysis->getTypeLabel(),
                'description' => 'Destinataire: ' . $analysis->getRecipientName(),
            ];
        }

        // Sort by date, most recent first
        usort($interactions, static fn ($a, $b) => $b['date'] <=> $a['date']);

        return $interactions;
    }

    /**
     * Get lead score based on interactions.
     */
    public function getLeadScore(): int
    {
        $score = 0;

        // Base prospect score
        $score += match ($this->status) {
            'lead' => 10,
            'prospect' => 20,
            'qualified' => 40,
            'negotiation' => 60,
            'customer' => 100,
            'lost' => 0,
            default => 5
        };

        // Contact requests scoring
        foreach ($this->contactRequests as $contact) {
            $score += match ($contact->getType()) {
                'quote' => 50,
                'advice' => 30,
                'information' => 20,
                'quick_registration' => 60,
                default => 15
            };
        }

        // Session registrations scoring (high intent)
        $score += count($this->sessionRegistrations) * 80;

        // Needs analysis scoring (Qualiopi compliance)
        foreach ($this->needsAnalysisRequests as $analysis) {
            $score += $analysis->isCompleted() ? 60 : 30;
        }

        // Multiple formations interest
        $score += count($this->interestedFormations) * 20;

        // Company email domain bonus
        if ($this->company && filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $domain = substr(strrchr($this->email, '@'), 1);
            if (!in_array($domain, ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'], true)) {
                $score += 10;
            }
        }

        return min($score, 999); // Cap at 999
    }

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

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

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(?string $company): static
    {
        $this->company = $company;

        return $this;
    }

    public function getPosition(): ?string
    {
        return $this->position;
    }

    public function setPosition(?string $position): static
    {
        $this->position = $position;

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

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getEstimatedBudget(): ?float
    {
        return $this->estimatedBudget;
    }

    public function setEstimatedBudget(?float $estimatedBudget): static
    {
        $this->estimatedBudget = $estimatedBudget;

        return $this;
    }

    public function getExpectedClosureDate(): ?DateTimeInterface
    {
        return $this->expectedClosureDate;
    }

    public function setExpectedClosureDate(?DateTimeInterface $expectedClosureDate): static
    {
        $this->expectedClosureDate = $expectedClosureDate;

        return $this;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getLastContactDate(): ?DateTimeInterface
    {
        return $this->lastContactDate;
    }

    public function setLastContactDate(?DateTimeInterface $lastContactDate): static
    {
        $this->lastContactDate = $lastContactDate;

        return $this;
    }

    public function getNextFollowUpDate(): ?DateTimeInterface
    {
        return $this->nextFollowUpDate;
    }

    public function setNextFollowUpDate(?DateTimeInterface $nextFollowUpDate): static
    {
        $this->nextFollowUpDate = $nextFollowUpDate;

        return $this;
    }

    public function getAssignedTo(): ?Admin
    {
        return $this->assignedTo;
    }

    public function setAssignedTo(?Admin $assignedTo): static
    {
        $this->assignedTo = $assignedTo;

        return $this;
    }

    /**
     * @return Collection<int, Formation>
     */
    public function getInterestedFormations(): Collection
    {
        return $this->interestedFormations;
    }

    /**
     * Alias for getInterestedFormations for template compatibility.
     */
    public function getFormations(): Collection
    {
        return $this->interestedFormations;
    }

    /**
     * @return Collection<int, Service>
     */
    public function getInterestedServices(): Collection
    {
        return $this->interestedServices;
    }

    /**
     * Alias for getInterestedServices for template compatibility.
     */
    public function getServices(): Collection
    {
        return $this->interestedServices;
    }

    /**
     * @return Collection<int, ProspectNote>
     */
    public function getNotes(): Collection
    {
        return $this->notes;
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): static
    {
        $this->customFields = $customFields;

        return $this;
    }

    /**
     * Add a formation to the prospect's interests.
     */
    public function addFormation(Formation $formation): static
    {
        if (!$this->interestedFormations->contains($formation)) {
            $this->interestedFormations->add($formation);
        }

        return $this;
    }

    /**
     * Remove a formation from the prospect's interests.
     */
    public function removeFormation(Formation $formation): static
    {
        $this->interestedFormations->removeElement($formation);

        return $this;
    }

    /**
     * Add a service to the prospect's interests.
     */
    public function addService(Service $service): static
    {
        if (!$this->interestedServices->contains($service)) {
            $this->interestedServices->add($service);
        }

        return $this;
    }

    /**
     * Remove a service from the prospect's interests.
     */
    public function removeService(Service $service): static
    {
        $this->interestedServices->removeElement($service);

        return $this;
    }

    public function getTags(): ?array
    {
        return $this->tags ?? [];
    }

    public function setTags(?array $tags): static
    {
        $this->tags = $tags;

        return $this;
    }
}
