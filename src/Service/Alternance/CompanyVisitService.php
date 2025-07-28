<?php

namespace App\Service\Alternance;

use App\Entity\Alternance\CompanyVisit;
use App\Entity\User\Mentor;
use App\Entity\User\Student;
use App\Entity\User\Teacher;
use App\Repository\Alternance\CompanyVisitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for managing company visits by pedagogical supervisors
 * 
 * Handles visit scheduling, reporting, and follow-up for apprenticeship
 * supervision, ensuring Qualiopi compliance for company-based training.
 */
class CompanyVisitService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CompanyVisitRepository $companyVisitRepository,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Schedule a company visit
     */
    public function scheduleVisit(
        Student $student,
        Teacher $visitor,
        Mentor $mentor,
        \DateTimeInterface $visitDate,
        string $visitType,
        array $objectivesToCheck = []
    ): CompanyVisit {
        $visit = new CompanyVisit();
        $visit->setStudent($student)
              ->setVisitor($visitor)
              ->setMentor($mentor)
              ->setVisitDate($visitDate)
              ->setVisitType($visitType)
              ->setObjectivesChecked($objectivesToCheck);

        $this->entityManager->persist($visit);
        $this->entityManager->flush();

        $this->logger->info('Company visit scheduled', [
            'visit_id' => $visit->getId(),
            'student_id' => $student->getId(),
            'company' => $mentor->getCompanyName(),
            'visit_date' => $visitDate->format('Y-m-d H:i:s'),
            'type' => $visitType
        ]);

        return $visit;
    }

    /**
     * Complete a company visit with full report
     */
    public function completeVisit(
        CompanyVisit $visit,
        array $observedActivities,
        array $strengths,
        array $improvementAreas,
        array $recommendations,
        string $visitReport,
        ?string $mentorFeedback = null,
        ?string $studentFeedback = null,
        ?int $duration = null,
        array $ratings = []
    ): CompanyVisit {
        $visit->setObservedActivities($observedActivities)
              ->setStrengths($strengths)
              ->setImprovementAreas($improvementAreas)
              ->setRecommendations($recommendations)
              ->setVisitReport($visitReport);

        if ($mentorFeedback) {
            $visit->setMentorFeedback($mentorFeedback);
        }

        if ($studentFeedback) {
            $visit->setStudentFeedback($studentFeedback);
        }

        if ($duration) {
            $visit->setDuration($duration);
        }

        // Set ratings if provided
        if (isset($ratings['overall'])) {
            $visit->setOverallRating($ratings['overall']);
        }
        if (isset($ratings['working_conditions'])) {
            $visit->setWorkingConditionsRating($ratings['working_conditions']);
        }
        if (isset($ratings['supervision'])) {
            $visit->setSupervisionRating($ratings['supervision']);
        }
        if (isset($ratings['integration'])) {
            $visit->setIntegrationRating($ratings['integration']);
        }

        // Determine if follow-up is needed
        $visit->setFollowUpRequired($this->determineFollowUpNeeded($visit));

        $this->entityManager->flush();

        $this->logger->info('Company visit completed', [
            'visit_id' => $visit->getId(),
            'average_rating' => $visit->getAverageRating(),
            'follow_up_required' => $visit->isFollowUpRequired(),
            'positive_outcome' => $visit->hasPositiveOutcome()
        ]);

        return $visit;
    }

    /**
     * Schedule follow-up visit
     */
    public function scheduleFollowUpVisit(
        CompanyVisit $originalVisit,
        \DateTimeInterface $followUpDate,
        string $followUpType = CompanyVisit::TYPE_FOLLOW_UP
    ): CompanyVisit {
        $followUpVisit = $this->scheduleVisit(
            $originalVisit->getStudent(),
            $originalVisit->getVisitor(),
            $originalVisit->getMentor(),
            $followUpDate,
            $followUpType,
            $this->extractFollowUpObjectives($originalVisit)
        );

        // Link to original visit in notes
        $followUpVisit->setNotes('Visite de suivi - Référence: Visite #' . $originalVisit->getId());

        // Update original visit to reference follow-up
        $originalVisit->setNextVisitDate($followUpDate);

        $this->entityManager->flush();

        $this->logger->info('Follow-up visit scheduled', [
            'original_visit_id' => $originalVisit->getId(),
            'follow_up_visit_id' => $followUpVisit->getId(),
            'follow_up_date' => $followUpDate->format('Y-m-d H:i:s')
        ]);

        return $followUpVisit;
    }

    /**
     * Get visit statistics for reporting
     */
    public function getVisitStatistics(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $baseStats = $this->companyVisitRepository->getVisitStatistics($startDate, $endDate);
        
        // Add additional analysis
        $lowRatingVisits = $this->companyVisitRepository->findVisitsWithLowRatings(6.0);
        $positiveOutcomes = $this->companyVisitRepository->findVisitsWithPositiveOutcomes(7.0);
        
        $baseStats['low_rating_visits'] = count($lowRatingVisits);
        $baseStats['positive_outcomes'] = count($positiveOutcomes);
        $baseStats['success_rate'] = $baseStats['total_visits'] > 0 ? 
            round((count($positiveOutcomes) / $baseStats['total_visits']) * 100, 2) : 0;

        return $baseStats;
    }

    /**
     * Find students needing visits
     */
    public function getStudentsNeedingVisits(int $daysSinceLastVisit = 60): array
    {
        return $this->companyVisitRepository->findStudentsNeedingVisits($daysSinceLastVisit);
    }

    /**
     * Get overdue follow-up visits
     */
    public function getOverdueFollowUps(): array
    {
        return $this->companyVisitRepository->findOverdueFollowUps();
    }

    /**
     * Generate visit report template
     */
    public function generateVisitReportTemplate(CompanyVisit $visit): array
    {
        return [
            'visit_info' => [
                'date' => $visit->getVisitDate()?->format('d/m/Y'),
                'type' => $visit->getVisitTypeLabel(),
                'duration' => $visit->getDurationFormatted(),
                'visitor' => $visit->getVisitor()?->getFullName(),
                'student' => $visit->getStudent()?->getFullName(),
                'mentor' => $visit->getMentor()?->getFullName(),
                'company' => $visit->getMentor()?->getCompanyName()
            ],
            'objectives_checked' => $visit->getObjectivesChecked(),
            'observed_activities' => [],
            'assessment' => [
                'strengths' => [],
                'improvement_areas' => [],
                'working_conditions_rating' => null,
                'supervision_rating' => null,
                'integration_rating' => null,
                'overall_rating' => null
            ],
            'feedback' => [
                'mentor_feedback' => '',
                'student_feedback' => ''
            ],
            'recommendations' => [],
            'follow_up' => [
                'required' => false,
                'next_visit_date' => null,
                'specific_points' => []
            ]
        ];
    }

    /**
     * Schedule regular visits for student alternance program
     */
    public function scheduleRegularVisits(
        Student $student,
        Teacher $visitor,
        Mentor $mentor,
        \DateTimeInterface $startDate,
        int $intervalMonths = 2,
        int $numberOfVisits = 4
    ): array {
        $visits = [];
        $currentDate = \DateTime::createFromInterface($startDate);

        for ($i = 0; $i < $numberOfVisits; $i++) {
            $visitType = match ($i) {
                0 => CompanyVisit::TYPE_INTEGRATION,
                $numberOfVisits - 1 => CompanyVisit::TYPE_FINAL_ASSESSMENT,
                default => CompanyVisit::TYPE_FOLLOW_UP
            };

            $visit = $this->scheduleVisit(
                $student,
                $visitor,
                $mentor,
                clone $currentDate,
                $visitType,
                $this->getDefaultObjectivesByType($visitType)
            );

            $visits[] = $visit;
            $currentDate->add(new \DateInterval('P' . $intervalMonths . 'M'));
        }

        $this->logger->info('Regular visits scheduled for student', [
            'student_id' => $student->getId(),
            'number_of_visits' => $numberOfVisits,
            'interval_months' => $intervalMonths
        ]);

        return $visits;
    }

    /**
     * Analyze visit trends for quality improvement
     */
    public function analyzeVisitTrends(int $months = 6): array
    {
        $trends = $this->companyVisitRepository->getMonthlyVisitTrends($months);
        
        $analysis = [
            'monthly_data' => $trends,
            'total_visits' => array_sum(array_column($trends, 'visit_count')),
            'average_monthly_visits' => count($trends) > 0 ? 
                round(array_sum(array_column($trends, 'visit_count')) / count($trends), 2) : 0,
            'rating_trend' => $this->calculateRatingTrend($trends),
            'recommendations' => $this->generateTrendRecommendations($trends)
        ];

        return $analysis;
    }

    /**
     * Determine if follow-up is needed based on visit outcome
     */
    private function determineFollowUpNeeded(CompanyVisit $visit): bool
    {
        // Follow-up required if:
        // - Average rating is below 6
        // - More than 2 improvement areas identified
        // - Specific concerns in working conditions or supervision
        // - Student or mentor feedback indicates issues

        $averageRating = $visit->getAverageRating();
        $improvementAreasCount = count($visit->getImprovementAreas());
        
        return $averageRating !== null && $averageRating < 6 ||
               $improvementAreasCount > 2 ||
               ($visit->getWorkingConditionsRating() && $visit->getWorkingConditionsRating() < 5) ||
               ($visit->getSupervisionRating() && $visit->getSupervisionRating() < 5);
    }

    /**
     * Extract follow-up objectives from previous visit
     */
    private function extractFollowUpObjectives(CompanyVisit $originalVisit): array
    {
        $followUpObjectives = [];
        
        // Add improvement areas as objectives for follow-up
        foreach ($originalVisit->getImprovementAreas() as $area) {
            $followUpObjectives[] = [
                'type' => 'improvement',
                'description' => 'Suivi: ' . $area,
                'reference_visit' => $originalVisit->getId()
            ];
        }

        // Add recommendations as objectives
        foreach ($originalVisit->getRecommendations() as $recommendation) {
            if (isset($recommendation['action_required']) && $recommendation['action_required']) {
                $followUpObjectives[] = [
                    'type' => 'recommendation_follow_up',
                    'description' => 'Vérification: ' . $recommendation['description'],
                    'reference_visit' => $originalVisit->getId()
                ];
            }
        }

        return $followUpObjectives;
    }

    /**
     * Get default objectives by visit type
     */
    private function getDefaultObjectivesByType(string $visitType): array
    {
        return match ($visitType) {
            CompanyVisit::TYPE_INTEGRATION => [
                ['description' => 'Vérifier l\'accueil et l\'intégration de l\'alternant'],
                ['description' => 'Évaluer l\'adaptation au poste de travail'],
                ['description' => 'S\'assurer de la compréhension des missions'],
                ['description' => 'Vérifier les conditions de travail']
            ],
            CompanyVisit::TYPE_EVALUATION => [
                ['description' => 'Évaluer la progression sur les compétences techniques'],
                ['description' => 'Mesurer l\'autonomie acquise'],
                ['description' => 'Vérifier l\'atteinte des objectifs pédagogiques'],
                ['description' => 'Évaluer les soft skills développées']
            ],
            CompanyVisit::TYPE_FINAL_ASSESSMENT => [
                ['description' => 'Bilan final des compétences acquises'],
                ['description' => 'Évaluation globale de la période d\'alternance'],
                ['description' => 'Perspectives d\'évolution professionnelle'],
                ['description' => 'Retour d\'expérience global']
            ],
            default => [
                ['description' => 'Faire le point sur la progression'],
                ['description' => 'Identifier les difficultés éventuelles'],
                ['description' => 'Vérifier la satisfaction mutuelle'],
                ['description' => 'Planifier les prochaines étapes']
            ]
        };
    }

    /**
     * Calculate rating trend over time
     */
    private function calculateRatingTrend(array $monthlyData): string
    {
        if (count($monthlyData) < 2) {
            return 'insufficient_data';
        }

        $ratings = array_filter(array_column($monthlyData, 'avg_rating'));
        
        if (count($ratings) < 2) {
            return 'insufficient_data';
        }

        $firstHalf = array_slice($ratings, 0, ceil(count($ratings) / 2));
        $secondHalf = array_slice($ratings, ceil(count($ratings) / 2));

        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);

        $difference = $secondAvg - $firstAvg;

        if ($difference > 0.5) {
            return 'improving';
        } elseif ($difference < -0.5) {
            return 'declining';
        } else {
            return 'stable';
        }
    }

    /**
     * Generate recommendations based on trends
     */
    private function generateTrendRecommendations(array $monthlyData): array
    {
        $recommendations = [];
        
        // Add specific recommendations based on trend analysis
        // This would be expanded based on business rules
        
        return $recommendations;
    }
}
