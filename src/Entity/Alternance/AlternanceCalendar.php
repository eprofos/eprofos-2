<?php

namespace App\Entity\Alternance;

use App\Entity\User\Student;
use App\Repository\AlternanceCalendarRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * AlternanceCalendar entity for managing week-by-week planning of alternance
 * 
 * Handles the scheduling of center/company periods, activities, evaluations,
 * meetings, holidays and notes for each week of the alternance contract.
 */
#[ORM\Entity(repositoryClass: AlternanceCalendarRepository::class)]
#[ORM\Table(name: 'alternance_calendars')]
#[ORM\UniqueConstraint(
    name: 'unique_student_week_year',
    columns: ['student_id', 'week', 'year']
)]
#[ORM\Index(columns: ['week', 'year'], name: 'idx_week_year')]
#[ORM\Index(columns: ['location'], name: 'idx_location')]
#[ORM\Index(columns: ['is_confirmed'], name: 'idx_confirmed')]
#[ORM\HasLifecycleCallbacks]
#[Gedmo\Loggable]
class AlternanceCalendar
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Student::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'L\'alternant est obligatoire.')]
    private ?Student $student = null;

    #[ORM\ManyToOne(targetEntity: AlternanceContract::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Le contrat d\'alternance est obligatoire.')]
    private ?AlternanceContract $contract = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\NotNull(message: 'Le numéro de semaine est obligatoire.')]
    #[Assert\Range(
        min: 1,
        max: 53,
        notInRangeMessage: 'Le numéro de semaine doit être entre {{ min }} et {{ max }}.'
    )]
    #[Gedmo\Versioned]
    private ?int $week = null;

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\NotNull(message: 'L\'année est obligatoire.')]
    #[Assert\Range(
        min: 2020,
        max: 2040,
        notInRangeMessage: 'L\'année doit être comprise entre {{ min }} et {{ max }}.'
    )]
    #[Gedmo\Versioned]
    private ?int $year = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Le lieu est obligatoire.')]
    #[Assert\Choice(
        choices: ['center', 'company'],
        message: 'Le lieu doit être "center" ou "company".'
    )]
    #[Gedmo\Versioned]
    private ?string $location = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Gedmo\Versioned]
    private ?array $centerSessions = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Gedmo\Versioned]
    private ?array $companyActivities = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Gedmo\Versioned]
    private ?array $evaluations = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Gedmo\Versioned]
    private ?array $meetings = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Gedmo\Versioned]
    private ?array $holidays = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'Les notes ne peuvent pas dépasser {{ limit }} caractères.'
    )]
    #[Gedmo\Versioned]
    private ?string $notes = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Gedmo\Versioned]
    private bool $isConfirmed = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeInterface $lastModified = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Gedmo\Versioned]
    private ?string $modifiedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'create')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Gedmo\Timestampable(on: 'update')]
    private ?\DateTimeInterface $updatedAt = null;

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

    public function getContract(): ?AlternanceContract
    {
        return $this->contract;
    }

    public function setContract(?AlternanceContract $contract): static
    {
        $this->contract = $contract;
        return $this;
    }

    public function getWeek(): ?int
    {
        return $this->week;
    }

    public function setWeek(?int $week): static
    {
        $this->week = $week;
        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(?int $year): static
    {
        $this->year = $year;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;
        return $this;
    }

    public function getCenterSessions(): ?array
    {
        return $this->centerSessions;
    }

    public function setCenterSessions(?array $centerSessions): static
    {
        $this->centerSessions = $centerSessions;
        return $this;
    }

    public function getCompanyActivities(): ?array
    {
        return $this->companyActivities;
    }

    public function setCompanyActivities(?array $companyActivities): static
    {
        $this->companyActivities = $companyActivities;
        return $this;
    }

    public function getEvaluations(): ?array
    {
        return $this->evaluations;
    }

    public function setEvaluations(?array $evaluations): static
    {
        $this->evaluations = $evaluations;
        return $this;
    }

    public function getMeetings(): ?array
    {
        return $this->meetings;
    }

    public function setMeetings(?array $meetings): static
    {
        $this->meetings = $meetings;
        return $this;
    }

    public function getHolidays(): ?array
    {
        return $this->holidays;
    }

    public function setHolidays(?array $holidays): static
    {
        $this->holidays = $holidays;
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

    public function isConfirmed(): bool
    {
        return $this->isConfirmed;
    }

    public function setIsConfirmed(bool $isConfirmed): static
    {
        $this->isConfirmed = $isConfirmed;
        return $this;
    }

    public function getLastModified(): ?\DateTimeInterface
    {
        return $this->lastModified;
    }

    public function setLastModified(?\DateTimeInterface $lastModified): static
    {
        $this->lastModified = $lastModified;
        return $this;
    }

    public function getModifiedBy(): ?string
    {
        return $this->modifiedBy;
    }

    public function setModifiedBy(?string $modifiedBy): static
    {
        $this->modifiedBy = $modifiedBy;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    // Business methods

    /**
     * Check if this week is at the training center
     */
    public function isCenterWeek(): bool
    {
        return $this->location === 'center';
    }

    /**
     * Check if this week is at the company
     */
    public function isCompanyWeek(): bool
    {
        return $this->location === 'company';
    }

    /**
     * Get the date range for this calendar week
     */
    public function getWeekDateRange(): array
    {
        $monday = new \DateTime();
        $monday->setISODate($this->year, $this->week);
        
        $friday = clone $monday;
        $friday->modify('+4 days');
        
        return [
            'start' => $monday,
            'end' => $friday
        ];
    }

    /**
     * Get formatted week period (e.g., "Semaine 15 (8-12 avril 2024)")
     */
    public function getFormattedWeekPeriod(): string
    {
        $dateRange = $this->getWeekDateRange();
        $startFormatted = $dateRange['start']->format('j');
        $endFormatted = $dateRange['end']->format('j F Y');
        
        return sprintf(
            'Semaine %d (%s-%s)',
            $this->week,
            $startFormatted,
            $endFormatted
        );
    }

    /**
     * Check if this week has any evaluations scheduled
     */
    public function hasEvaluations(): bool
    {
        return !empty($this->evaluations);
    }

    /**
     * Check if this week has any meetings scheduled
     */
    public function hasMeetings(): bool
    {
        return !empty($this->meetings);
    }

    /**
     * Check if this week contains holidays
     */
    public function hasHolidays(): bool
    {
        return !empty($this->holidays);
    }

    /**
     * Get all scheduled activities for this week (sessions, activities, meetings, evaluations)
     */
    public function getAllActivities(): array
    {
        $activities = [];
        
        if ($this->centerSessions) {
            foreach ($this->centerSessions as $session) {
                $activities[] = array_merge($session, ['type' => 'center_session']);
            }
        }
        
        if ($this->companyActivities) {
            foreach ($this->companyActivities as $activity) {
                $activities[] = array_merge($activity, ['type' => 'company_activity']);
            }
        }
        
        if ($this->meetings) {
            foreach ($this->meetings as $meeting) {
                $activities[] = array_merge($meeting, ['type' => 'meeting']);
            }
        }
        
        if ($this->evaluations) {
            foreach ($this->evaluations as $evaluation) {
                $activities[] = array_merge($evaluation, ['type' => 'evaluation']);
            }
        }
        
        // Sort by date/time if available
        usort($activities, function($a, $b) {
            $timeA = $a['time'] ?? '08:00';
            $timeB = $b['time'] ?? '08:00';
            return strcmp($timeA, $timeB);
        });
        
        return $activities;
    }

    /**
     * Add a center session to this week
     */
    public function addCenterSession(array $session): static
    {
        if (!$this->centerSessions) {
            $this->centerSessions = [];
        }
        $this->centerSessions[] = $session;
        return $this;
    }

    /**
     * Add a company activity to this week
     */
    public function addCompanyActivity(array $activity): static
    {
        if (!$this->companyActivities) {
            $this->companyActivities = [];
        }
        $this->companyActivities[] = $activity;
        return $this;
    }

    /**
     * Add an evaluation to this week
     */
    public function addEvaluation(array $evaluation): static
    {
        if (!$this->evaluations) {
            $this->evaluations = [];
        }
        $this->evaluations[] = $evaluation;
        return $this;
    }

    /**
     * Add a meeting to this week
     */
    public function addMeeting(array $meeting): static
    {
        if (!$this->meetings) {
            $this->meetings = [];
        }
        $this->meetings[] = $meeting;
        return $this;
    }

    /**
     * Add a holiday to this week
     */
    public function addHoliday(array $holiday): static
    {
        if (!$this->holidays) {
            $this->holidays = [];
        }
        $this->holidays[] = $holiday;
        return $this;
    }

    /**
     * Get the location as a localized string
     */
    public function getLocationLabel(): string
    {
        return $this->location === 'center' ? 'Centre de formation' : 'Entreprise';
    }

    /**
     * Check if this calendar entry conflicts with another one
     */
    public function hasConflictWith(AlternanceCalendar $other): bool
    {
        return $this->student === $other->getStudent() 
            && $this->week === $other->getWeek() 
            && $this->year === $other->getYear()
            && $this->id !== $other->getId();
    }

    /**
     * Get validation status (confirmed or pending)
     */
    public function getValidationStatus(): string
    {
        return $this->isConfirmed ? 'Confirmé' : 'En attente';
    }

    /**
     * Get CSS class for location styling
     */
    public function getLocationCssClass(): string
    {
        return $this->location === 'center' ? 'location-center' : 'location-company';
    }
}
