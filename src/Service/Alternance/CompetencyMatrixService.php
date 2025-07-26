<?php

namespace App\Service\Alternance;

use App\Entity\User\Student;
use App\Entity\Alternance\SkillsAssessment;
use App\Entity\Alternance\ProgressAssessment;
use App\Repository\Alternance\SkillsAssessmentRepository;
use App\Repository\Alternance\ProgressAssessmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * CompetencyMatrixService
 * 
 * Manages competency matrix generation, skills tracking, and certification badges
 * for the alternance portfolio system.
 */
class CompetencyMatrixService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SkillsAssessmentRepository $skillsAssessmentRepository,
        private ProgressAssessmentRepository $progressAssessmentRepository,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Generate complete competency matrix for a student
     */
    public function generateCompetencyMatrix(Student $student): array
    {
        $assessments = $this->skillsAssessmentRepository->findBy(
            ['student' => $student],
            ['assessmentDate' => 'ASC']
        );

        $progressAssessments = $this->progressAssessmentRepository->findBy(
            ['student' => $student],
            ['period' => 'ASC']
        );

        $matrix = [
            'student' => [
                'id' => $student->getId(),
                'name' => $student->getFullName(),
                'email' => $student->getEmail()
            ],
            'competency_areas' => [],
            'skills_progression' => [],
            'certifications' => [],
            'badges_earned' => [],
            'overall_progress' => [],
            'recommendations' => []
        ];

        // Competency areas from skills assessments
        $competencyAreas = $this->extractCompetencyAreas($assessments);
        $matrix['competency_areas'] = $competencyAreas;

        // Skills progression over time
        $matrix['skills_progression'] = $this->calculateSkillsProgression($assessments);

        // Extract certifications and badges
        $matrix['certifications'] = $this->extractCertifications($assessments);
        $matrix['badges_earned'] = $this->calculateEarnedBadges($assessments, $progressAssessments);

        // Overall progress from progress assessments
        $matrix['overall_progress'] = $this->extractOverallProgress($progressAssessments);

        // Generate recommendations
        $matrix['recommendations'] = $this->generateMatrixRecommendations($competencyAreas, $assessments);

        return $matrix;
    }

    /**
     * Get skills portfolio for student dashboard
     */
    public function getSkillsPortfolio(Student $student): array
    {
        $matrix = $this->generateCompetencyMatrix($student);
        
        return [
            'student_info' => $matrix['student'],
            'current_level' => $this->calculateCurrentLevel($matrix),
            'skills_summary' => $this->generateSkillsSummary($matrix),
            'achievements' => [
                'badges' => $matrix['badges_earned'],
                'certifications' => $matrix['certifications']
            ],
            'progression_chart_data' => $this->prepareProgressionChartData($matrix),
            'competency_radar_data' => $this->prepareCompetencyRadarData($matrix),
            'next_objectives' => $this->getNextObjectives($student),
            'portfolio_score' => $this->calculatePortfolioScore($matrix)
        ];
    }

    /**
     * Generate competency badge based on assessment results
     */
    public function generateCompetencyBadge(Student $student, string $competencyCode, array $assessmentData): array
    {
        $badgeData = [
            'competency_code' => $competencyCode,
            'competency_name' => $this->getCompetencyName($competencyCode),
            'level_achieved' => $this->calculateCompetencyLevel($assessmentData),
            'date_earned' => new \DateTime(),
            'evidence' => [],
            'badge_type' => $this->determineBadgeType($competencyCode),
            'validation_source' => 'cross_evaluation'
        ];

        // Collect evidence from assessments
        foreach ($assessmentData as $assessment) {
            if ($assessment instanceof SkillsAssessment) {
                $centerScores = $assessment->getCenterScores();
                $companyScores = $assessment->getCompanyScores();
                
                if (isset($centerScores[$competencyCode])) {
                    $badgeData['evidence'][] = [
                        'source' => 'centre_formation',
                        'score' => $centerScores[$competencyCode]['score'] ?? null,
                        'comment' => $centerScores[$competencyCode]['comment'] ?? null,
                        'date' => $assessment->getAssessmentDate()->format('Y-m-d')
                    ];
                }
                
                if (isset($companyScores[$competencyCode])) {
                    $badgeData['evidence'][] = [
                        'source' => 'entreprise',
                        'score' => $companyScores[$competencyCode]['score'] ?? null,
                        'comment' => $companyScores[$competencyCode]['comment'] ?? null,
                        'date' => $assessment->getAssessmentDate()->format('Y-m-d')
                    ];
                }
            }
        }

        return $badgeData;
    }

    /**
     * Extract competency areas from skills assessments
     */
    private function extractCompetencyAreas(array $assessments): array
    {
        $areas = [];
        
        foreach ($assessments as $assessment) {
            $globalCompetencies = $assessment->getGlobalCompetencies();
            
            foreach ($globalCompetencies as $code => $competency) {
                if (!isset($areas[$code])) {
                    $areas[$code] = [
                        'code' => $code,
                        'name' => $competency['name'] ?? $code,
                        'current_level' => 0,
                        'target_level' => 0,
                        'assessments_count' => 0,
                        'progression_history' => []
                    ];
                }
                
                $areas[$code]['current_level'] = max(
                    $areas[$code]['current_level'],
                    $competency['current_level'] ?? 0
                );
                
                $areas[$code]['target_level'] = max(
                    $areas[$code]['target_level'],
                    $competency['target_level'] ?? 0
                );
                
                $areas[$code]['assessments_count']++;
                
                $areas[$code]['progression_history'][] = [
                    'date' => $assessment->getAssessmentDate()->format('Y-m-d'),
                    'level' => $competency['current_level'] ?? 0,
                    'context' => $assessment->getContext()
                ];
            }
        }
        
        return array_values($areas);
    }

    /**
     * Calculate skills progression over time
     */
    private function calculateSkillsProgression(array $assessments): array
    {
        $progression = [];
        
        foreach ($assessments as $assessment) {
            $date = $assessment->getAssessmentDate()->format('Y-m-d');
            $skillsEvaluated = $assessment->getSkillsEvaluated();
            
            foreach ($skillsEvaluated as $skill) {
                $skillCode = $skill['code'] ?? 'unknown';
                
                if (!isset($progression[$skillCode])) {
                    $progression[$skillCode] = [
                        'code' => $skillCode,
                        'name' => $skill['name'] ?? $skillCode,
                        'category' => $skill['category'] ?? 'general',
                        'timeline' => []
                    ];
                }
                
                // Get scores from center and company
                $centerScores = $assessment->getCenterScores();
                $companyScores = $assessment->getCompanyScores();
                
                $centerScore = isset($centerScores[$skillCode]) ? 
                    (float) $centerScores[$skillCode]['score'] : null;
                $companyScore = isset($companyScores[$skillCode]) ? 
                    (float) $companyScores[$skillCode]['score'] : null;
                
                $progression[$skillCode]['timeline'][] = [
                    'date' => $date,
                    'center_score' => $centerScore,
                    'company_score' => $companyScore,
                    'average_score' => $this->calculateAverageScore($centerScore, $companyScore),
                    'context' => $assessment->getContext()
                ];
            }
        }
        
        return array_values($progression);
    }

    /**
     * Extract certifications from assessments
     */
    private function extractCertifications(array $assessments): array
    {
        $certifications = [];
        
        foreach ($assessments as $assessment) {
            if ($assessment->getAssessmentType() === 'certification' && 
                $assessment->getOverallRating() === 'excellent') {
                
                $certifications[] = [
                    'type' => 'competency_certification',
                    'title' => 'Certification de compétences - ' . $assessment->getAssessmentDate()->format('Y-m'),
                    'date_earned' => $assessment->getAssessmentDate()->format('Y-m-d'),
                    'skills_certified' => count($assessment->getSkillsEvaluated()),
                    'overall_rating' => $assessment->getOverallRating(),
                    'issuing_authority' => 'Centre de formation + Entreprise',
                    'verification_id' => 'CERT-' . $assessment->getId() . '-' . date('Y')
                ];
            }
        }
        
        return $certifications;
    }

    /**
     * Calculate earned badges based on skills progression
     */
    private function calculateEarnedBadges(array $skillsAssessments, array $progressAssessments): array
    {
        $badges = [];
        
        // Technical skill badges
        $technicalSkills = $this->extractTechnicalSkills($skillsAssessments);
        foreach ($technicalSkills as $skillCode => $skillData) {
            if ($skillData['max_score'] >= 4.0) {
                $badges[] = [
                    'type' => 'technical_skill',
                    'code' => $skillCode,
                    'title' => 'Maîtrise - ' . $skillData['name'],
                    'level' => $this->determineBadgeLevel($skillData['max_score']),
                    'date_earned' => $skillData['date_achieved'],
                    'icon' => 'tech-badge'
                ];
            }
        }
        
        // Progression badges
        foreach ($progressAssessments as $progressAssessment) {
            $overallProgression = (float) $progressAssessment->getOverallProgression();
            
            if ($overallProgression >= 25 && $overallProgression < 50) {
                $badges[] = [
                    'type' => 'progression',
                    'code' => 'quarter_progress',
                    'title' => 'Premier Quart - 25% de progression',
                    'level' => 'bronze',
                    'date_earned' => $progressAssessment->getPeriod()->format('Y-m-d'),
                    'icon' => 'progress-badge'
                ];
            } elseif ($overallProgression >= 50 && $overallProgression < 75) {
                $badges[] = [
                    'type' => 'progression',
                    'code' => 'half_progress',
                    'title' => 'Mi-parcours - 50% de progression',
                    'level' => 'silver',
                    'date_earned' => $progressAssessment->getPeriod()->format('Y-m-d'),
                    'icon' => 'progress-badge'
                ];
            } elseif ($overallProgression >= 75) {
                $badges[] = [
                    'type' => 'progression',
                    'code' => 'advanced_progress',
                    'title' => 'Progression Avancée - 75% de progression',
                    'level' => 'gold',
                    'date_earned' => $progressAssessment->getPeriod()->format('Y-m-d'),
                    'icon' => 'progress-badge'
                ];
            }
        }
        
        return array_unique($badges, SORT_REGULAR);
    }

    /**
     * Extract overall progress from progress assessments
     */
    private function extractOverallProgress(array $progressAssessments): array
    {
        $progress = [];
        
        foreach ($progressAssessments as $assessment) {
            $progress[] = [
                'date' => $assessment->getPeriod()->format('Y-m-d'),
                'center_progression' => (float) $assessment->getCenterProgression(),
                'company_progression' => (float) $assessment->getCompanyProgression(),
                'overall_progression' => (float) $assessment->getOverallProgression(),
                'risk_level' => $assessment->getRiskLevel(),
                'objectives_completion' => $assessment->calculateObjectivesCompletionRate()
            ];
        }
        
        return $progress;
    }

    /**
     * Generate matrix-based recommendations
     */
    private function generateMatrixRecommendations(array $competencyAreas, array $assessments): array
    {
        $recommendations = [];
        
        // Analyze competency gaps
        foreach ($competencyAreas as $area) {
            $gap = $area['target_level'] - $area['current_level'];
            
            if ($gap > 1) {
                $recommendations[] = [
                    'type' => 'competency_gap',
                    'priority' => 'high',
                    'title' => 'Écart de compétence détecté',
                    'description' => "Écart de {$gap} niveaux pour {$area['name']}",
                    'suggested_actions' => [
                        'Formation complémentaire ciblée',
                        'Exercices pratiques renforcés',
                        'Mentorat spécialisé'
                    ]
                ];
            }
        }
        
        // Analyze recent assessment trends
        if (count($assessments) >= 2) {
            $recent = array_slice($assessments, -2);
            $improvement = $this->calculateImprovementTrend($recent);
            
            if ($improvement < 0) {
                $recommendations[] = [
                    'type' => 'declining_performance',
                    'priority' => 'medium',
                    'title' => 'Tendance à la baisse détectée',
                    'description' => 'Les dernières évaluations montrent une régression',
                    'suggested_actions' => [
                        'Révision des objectifs',
                        'Accompagnement personnalisé',
                        'Identification des difficultés'
                    ]
                ];
            }
        }
        
        return $recommendations;
    }

    /**
     * Prepare data for progression charts
     */
    private function prepareProgressionChartData(array $matrix): array
    {
        $chartData = [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Progression Centre',
                    'data' => [],
                    'borderColor' => '#3498db',
                    'backgroundColor' => 'rgba(52, 152, 219, 0.1)'
                ],
                [
                    'label' => 'Progression Entreprise',
                    'data' => [],
                    'borderColor' => '#e74c3c',
                    'backgroundColor' => 'rgba(231, 76, 60, 0.1)'
                ],
                [
                    'label' => 'Progression Globale',
                    'data' => [],
                    'borderColor' => '#2ecc71',
                    'backgroundColor' => 'rgba(46, 204, 113, 0.1)'
                ]
            ]
        ];
        
        foreach ($matrix['overall_progress'] as $progress) {
            $chartData['labels'][] = $progress['date'];
            $chartData['datasets'][0]['data'][] = $progress['center_progression'];
            $chartData['datasets'][1]['data'][] = $progress['company_progression'];
            $chartData['datasets'][2]['data'][] = $progress['overall_progression'];
        }
        
        return $chartData;
    }

    /**
     * Prepare data for competency radar chart
     */
    private function prepareCompetencyRadarData(array $matrix): array
    {
        $radarData = [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Niveau Actuel',
                    'data' => [],
                    'borderColor' => '#3498db',
                    'backgroundColor' => 'rgba(52, 152, 219, 0.2)'
                ],
                [
                    'label' => 'Niveau Cible',
                    'data' => [],
                    'borderColor' => '#e74c3c',
                    'backgroundColor' => 'rgba(231, 76, 60, 0.2)'
                ]
            ]
        ];
        
        foreach ($matrix['competency_areas'] as $area) {
            $radarData['labels'][] = $area['name'];
            $radarData['datasets'][0]['data'][] = $area['current_level'];
            $radarData['datasets'][1]['data'][] = $area['target_level'];
        }
        
        return $radarData;
    }

    // Helper methods
    private function calculateAverageScore(?float $centerScore, ?float $companyScore): ?float
    {
        if ($centerScore === null && $companyScore === null) {
            return null;
        }
        
        if ($centerScore === null) {
            return $companyScore;
        }
        
        if ($companyScore === null) {
            return $centerScore;
        }
        
        return ($centerScore + $companyScore) / 2;
    }

    private function getCompetencyName(string $competencyCode): string
    {
        $competencyNames = [
            'technical_mastery' => 'Maîtrise technique',
            'project_execution' => 'Exécution de projet',
            'collaboration' => 'Collaboration',
            'communication' => 'Communication',
            'problem_solving' => 'Résolution de problèmes',
            'innovation' => 'Innovation'
        ];
        
        return $competencyNames[$competencyCode] ?? $competencyCode;
    }

    private function calculateCurrentLevel(array $matrix): array
    {
        $totalAreas = count($matrix['competency_areas']);
        if ($totalAreas === 0) {
            return ['level' => 1, 'percentage' => 0];
        }
        
        $averageLevel = array_sum(array_column($matrix['competency_areas'], 'current_level')) / $totalAreas;
        
        return [
            'level' => round($averageLevel, 1),
            'percentage' => min(100, round(($averageLevel / 5) * 100, 1))
        ];
    }

    private function generateSkillsSummary(array $matrix): array
    {
        $summary = [
            'total_skills_evaluated' => 0,
            'mastered_skills' => 0,
            'in_progress_skills' => 0,
            'skills_by_category' => []
        ];
        
        foreach ($matrix['skills_progression'] as $skill) {
            $summary['total_skills_evaluated']++;
            
            $latestScore = end($skill['timeline'])['average_score'] ?? 0;
            if ($latestScore >= 4.0) {
                $summary['mastered_skills']++;
            } else {
                $summary['in_progress_skills']++;
            }
            
            $category = $skill['category'];
            if (!isset($summary['skills_by_category'][$category])) {
                $summary['skills_by_category'][$category] = 0;
            }
            $summary['skills_by_category'][$category]++;
        }
        
        return $summary;
    }

    private function getNextObjectives(Student $student): array
    {
        $latestProgress = $this->progressAssessmentRepository->findLatestByStudent($student);
        
        if (!$latestProgress) {
            return [];
        }
        
        return array_slice($latestProgress->getPendingObjectives(), 0, 3);
    }

    private function calculatePortfolioScore(array $matrix): int
    {
        $scores = [];
        
        // Competency level score (40%)
        $competencyScore = 0;
        foreach ($matrix['competency_areas'] as $area) {
            $competencyScore += ($area['current_level'] / 5) * 100;
        }
        if (count($matrix['competency_areas']) > 0) {
            $scores[] = ($competencyScore / count($matrix['competency_areas'])) * 0.4;
        }
        
        // Badges score (30%)
        $badgesScore = min(100, count($matrix['badges_earned']) * 10);
        $scores[] = $badgesScore * 0.3;
        
        // Progression score (30%)
        $progressionScore = 0;
        if (!empty($matrix['overall_progress'])) {
            $latestProgress = end($matrix['overall_progress']);
            $progressionScore = $latestProgress['overall_progression'];
        }
        $scores[] = $progressionScore * 0.3;
        
        return min(100, max(0, round(array_sum($scores))));
    }

    private function extractTechnicalSkills(array $assessments): array
    {
        $technicalSkills = [];
        
        foreach ($assessments as $assessment) {
            $skillsEvaluated = $assessment->getSkillsEvaluated();
            $centerScores = $assessment->getCenterScores();
            $companyScores = $assessment->getCompanyScores();
            
            foreach ($skillsEvaluated as $skill) {
                if ($skill['category'] === 'technique') {
                    $skillCode = $skill['code'];
                    $centerScore = isset($centerScores[$skillCode]) ? 
                        (float) $centerScores[$skillCode]['score'] : 0;
                    $companyScore = isset($companyScores[$skillCode]) ? 
                        (float) $companyScores[$skillCode]['score'] : 0;
                    
                    $maxScore = max($centerScore, $companyScore);
                    
                    if (!isset($technicalSkills[$skillCode]) || 
                        $maxScore > $technicalSkills[$skillCode]['max_score']) {
                        
                        $technicalSkills[$skillCode] = [
                            'name' => $skill['name'],
                            'max_score' => $maxScore,
                            'date_achieved' => $assessment->getAssessmentDate()->format('Y-m-d')
                        ];
                    }
                }
            }
        }
        
        return $technicalSkills;
    }

    private function determineBadgeLevel(float $score): string
    {
        if ($score >= 4.5) return 'gold';
        if ($score >= 4.0) return 'silver';
        if ($score >= 3.5) return 'bronze';
        return 'participation';
    }

    private function calculateCompetencyLevel(array $assessmentData): int
    {
        $scores = [];
        foreach ($assessmentData as $assessment) {
            if ($assessment instanceof SkillsAssessment) {
                $globalCompetencies = $assessment->getGlobalCompetencies();
                foreach ($globalCompetencies as $competency) {
                    $scores[] = $competency['current_level'] ?? 0;
                }
            }
        }
        
        return empty($scores) ? 1 : (int) round(array_sum($scores) / count($scores));
    }

    private function determineBadgeType(string $competencyCode): string
    {
        $technicalCodes = ['PROG_', 'DB_', 'WEB_', 'API_', 'DEVOPS_', 'CLOUD_'];
        
        foreach ($technicalCodes as $prefix) {
            if (strpos($competencyCode, $prefix) === 0) {
                return 'technical';
            }
        }
        
        return 'transversal';
    }

    private function calculateImprovementTrend(array $recentAssessments): float
    {
        if (count($recentAssessments) < 2) {
            return 0;
        }
        
        $first = $recentAssessments[0];
        $second = $recentAssessments[1];
        
        // Calculate average scores for both assessments
        $firstAvg = $this->calculateAssessmentAverage($first);
        $secondAvg = $this->calculateAssessmentAverage($second);
        
        return $secondAvg - $firstAvg;
    }

    private function calculateAssessmentAverage(SkillsAssessment $assessment): float
    {
        $centerScores = $assessment->getCenterScores();
        $companyScores = $assessment->getCompanyScores();
        
        $allScores = [];
        
        foreach ($centerScores as $score) {
            $allScores[] = (float) $score['score'];
        }
        
        foreach ($companyScores as $score) {
            $allScores[] = (float) $score['score'];
        }
        
        return empty($allScores) ? 0 : array_sum($allScores) / count($allScores);
    }
}
