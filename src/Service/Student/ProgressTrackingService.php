<?php

declare(strict_types=1);

namespace App\Service\Student;

use App\Entity\Core\StudentEnrollment;
use App\Entity\Core\StudentProgress;
use App\Entity\Training\Course;
use App\Entity\Training\Exercise;
use App\Entity\Training\QCM;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Progress Tracking Service
 *
 * Enhanced service for real-time, granular tracking of student advancement 
 * through all content levels with detailed analytics and Qualiopi-compliant reporting.
 * 
 * This service implements the requirements from Issue #67 - Progress Tracking Enhancement.
 */
class ProgressTrackingService
{
    /**
     * Milestone definitions for achievement tracking.
     */
    public const MILESTONES = [
        'first_course_completed' => [
            'title' => 'Premier cours terminé',
            'description' => 'Vous avez terminé votre premier cours !',
            'points' => 10
        ],
        'first_exercise_submitted' => [
            'title' => 'Premier exercice soumis',
            'description' => 'Vous avez soumis votre premier exercice !',
            'points' => 15
        ],
        'first_qcm_passed' => [
            'title' => 'Premier QCM réussi',
            'description' => 'Vous avez réussi votre premier QCM !',
            'points' => 20
        ],
        'chapter_completed' => [
            'title' => 'Chapitre terminé',
            'description' => 'Vous avez terminé un chapitre complet',
            'points' => 25
        ],
        'module_completed' => [
            'title' => 'Module terminé',
            'description' => 'Vous avez terminé un module complet',
            'points' => 50
        ],
        'formation_halfway' => [
            'title' => 'À mi-parcours',
            'description' => 'Vous avez atteint 50% de votre formation',
            'points' => 100
        ],
        'formation_three_quarters' => [
            'title' => 'Trois quarts accomplis',
            'description' => 'Vous avez atteint 75% de votre formation',
            'points' => 150
        ],
        'streak_7_days' => [
            'title' => 'Une semaine d\'assiduité',
            'description' => 'Vous vous êtes connecté 7 jours consécutifs',
            'points' => 30
        ],
        'streak_30_days' => [
            'title' => 'Un mois d\'assiduité',
            'description' => 'Vous vous êtes connecté 30 jours consécutifs',
            'points' => 100
        ],
        'high_engagement' => [
            'title' => 'Étudiant engagé',
            'description' => 'Vous maintenez un score d\'engagement supérieur à 80%',
            'points' => 75
        ]
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir
    ) {
    }

    /**
     * Record when a student views a course.
     */
    public function recordCourseView(StudentEnrollment $enrollment, Course $course): void
    {
        $progress = $enrollment->getProgress();
        if (!$progress) {
            $progress = $this->createProgressForEnrollment($enrollment);
        }

        $courseProgress = $progress->getCourseProgress();
        $courseId = $course->getId();
        $now = new DateTime();

        // Initialize or update course progress
        if (!isset($courseProgress[$courseId])) {
            $courseProgress[$courseId] = [
                'viewed' => false,
                'completed' => false,
                'timeSpent' => 0,
                'viewCount' => 0,
                'firstViewedAt' => null,
                'lastAccessed' => null
            ];
        }

        $courseProgress[$courseId]['viewed'] = true;
        $courseProgress[$courseId]['viewCount']++;
        $courseProgress[$courseId]['lastAccessed'] = $now->format('Y-m-d H:i:s');

        if (!$courseProgress[$courseId]['firstViewedAt']) {
            $courseProgress[$courseId]['firstViewedAt'] = $now->format('Y-m-d H:i:s');
        }

        $progress->setCourseProgress($courseProgress);
        $progress->updateActivity();

        $this->entityManager->persist($progress);
        $this->entityManager->flush();

        $this->logger->info('Course view recorded', [
            'student_id' => $enrollment->getStudent()?->getId(),
            'course_id' => $courseId,
            'enrollment_id' => $enrollment->getId()
        ]);
    }

    /**
     * Record when a student completes a course.
     */
    public function recordCourseCompletion(StudentEnrollment $enrollment, Course $course): void
    {
        $progress = $enrollment->getProgress();
        if (!$progress) {
            $progress = $this->createProgressForEnrollment($enrollment);
        }

        $courseProgress = $progress->getCourseProgress();
        $courseId = $course->getId();
        $now = new DateTime();

        // Update course completion
        if (!isset($courseProgress[$courseId])) {
            $this->recordCourseView($enrollment, $course);
            $courseProgress = $progress->getCourseProgress();
        }

        $courseProgress[$courseId]['completed'] = true;
        $courseProgress[$courseId]['completedAt'] = $now->format('Y-m-d H:i:s');

        $progress->setCourseProgress($courseProgress);
        $this->updateChapterProgress($progress, $course->getChapter());
        $this->updateModuleProgress($progress, $course->getChapter()?->getModule());
        $this->updateOverallProgress($progress);

        $this->entityManager->persist($progress);
        $this->entityManager->flush();

        // Check for milestones
        $milestones = $this->checkMilestones($enrollment);
        
        $this->logger->info('Course completion recorded', [
            'student_id' => $enrollment->getStudent()?->getId(),
            'course_id' => $courseId,
            'enrollment_id' => $enrollment->getId(),
            'milestones_achieved' => array_keys($milestones)
        ]);
    }

    /**
     * Record exercise submission with detailed tracking.
     */
    public function recordExerciseSubmission(StudentEnrollment $enrollment, Exercise $exercise, array $submission): void
    {
        $progress = $enrollment->getProgress();
        if (!$progress) {
            $progress = $this->createProgressForEnrollment($enrollment);
        }

        $exerciseProgress = $this->getExerciseProgress($progress);
        $exerciseId = $exercise->getId();
        $now = new DateTime();

        // Initialize or update exercise progress
        if (!isset($exerciseProgress[$exerciseId])) {
            $exerciseProgress[$exerciseId] = [
                'attempted' => false,
                'submitted' => false,
                'attempts' => 0,
                'timeSpent' => 0,
                'submissions' => []
            ];
        }

        $exerciseProgress[$exerciseId]['attempted'] = true;
        $exerciseProgress[$exerciseId]['submitted'] = true;
        $exerciseProgress[$exerciseId]['attempts']++;
        $exerciseProgress[$exerciseId]['submissions'][] = [
            'submittedAt' => $now->format('Y-m-d H:i:s'),
            'content' => $submission,
            'status' => 'submitted'
        ];

        $this->setExerciseProgress($progress, $exerciseProgress);
        $progress->updateActivity();

        $this->entityManager->persist($progress);
        $this->entityManager->flush();

        // Check for milestones
        $this->checkMilestones($enrollment);

        $this->logger->info('Exercise submission recorded', [
            'student_id' => $enrollment->getStudent()?->getId(),
            'exercise_id' => $exerciseId,
            'enrollment_id' => $enrollment->getId(),
            'attempt_number' => $exerciseProgress[$exerciseId]['attempts']
        ]);
    }

    /**
     * Record QCM attempt with score and detailed answers.
     */
    public function recordQCMAttempt(StudentEnrollment $enrollment, QCM $qcm, array $answers, int $score): void
    {
        $progress = $enrollment->getProgress();
        if (!$progress) {
            $progress = $this->createProgressForEnrollment($enrollment);
        }

        $qcmProgress = $this->getQCMProgress($progress);
        $qcmId = $qcm->getId();
        $now = new DateTime();
        $maxScore = 100; // Assuming 100 as max score, adjust as needed

        // Initialize or update QCM progress
        if (!isset($qcmProgress[$qcmId])) {
            $qcmProgress[$qcmId] = [
                'attempts' => [],
                'bestScore' => 0,
                'passed' => false,
                'timeSpent' => 0
            ];
        }

        // Add new attempt
        $attempt = [
            'attemptAt' => $now->format('Y-m-d H:i:s'),
            'score' => $score,
            'maxScore' => $maxScore,
            'timeSpent' => 0, // This should be calculated from actual time tracking
            'answers' => $answers,
            'passed' => $score >= ($qcm->getPassingScore() ?? 60)
        ];

        $qcmProgress[$qcmId]['attempts'][] = $attempt;
        $qcmProgress[$qcmId]['bestScore'] = max($qcmProgress[$qcmId]['bestScore'], $score);
        $qcmProgress[$qcmId]['passed'] = $qcmProgress[$qcmId]['bestScore'] >= ($qcm->getPassingScore() ?? 60);

        $this->setQCMProgress($progress, $qcmProgress);
        $progress->updateActivity();

        $this->entityManager->persist($progress);
        $this->entityManager->flush();

        // Check for milestones
        $this->checkMilestones($enrollment);

        $this->logger->info('QCM attempt recorded', [
            'student_id' => $enrollment->getStudent()?->getId(),
            'qcm_id' => $qcmId,
            'enrollment_id' => $enrollment->getId(),
            'score' => $score,
            'passed' => $attempt['passed']
        ]);
    }

    /**
     * Update time spent on specific content.
     */
    public function updateTimeSpent(StudentEnrollment $enrollment, object $content, int $seconds): void
    {
        $progress = $enrollment->getProgress();
        if (!$progress) {
            $progress = $this->createProgressForEnrollment($enrollment);
        }

        // Add to total time spent
        $progress->addTimeSpent((int) ceil($seconds / 60)); // Convert to minutes

        // Update specific content time tracking
        $timeTracking = $this->getTimeSpentTracking($progress);
        $contentType = $this->getContentType($content);
        $contentId = $content->getId();

        if (!isset($timeTracking[$contentType])) {
            $timeTracking[$contentType] = [];
        }

        if (!isset($timeTracking[$contentType][$contentId])) {
            $timeTracking[$contentType][$contentId] = 0;
        }

        $timeTracking[$contentType][$contentId] += $seconds;
        $this->setTimeSpentTracking($progress, $timeTracking);

        $this->entityManager->persist($progress);
        $this->entityManager->flush();

        $this->logger->debug('Time spent updated', [
            'student_id' => $enrollment->getStudent()?->getId(),
            'content_type' => $contentType,
            'content_id' => $contentId,
            'seconds_added' => $seconds
        ]);
    }

    /**
     * Calculate overall progress from all completed content.
     */
    public function calculateOverallProgress(StudentEnrollment $enrollment): float
    {
        $progress = $enrollment->getProgress();
        if (!$progress) {
            return 0.0;
        }

        $formation = $enrollment->getFormation();
        if (!$formation) {
            return 0.0;
        }

        $totalItems = 0;
        $completedItems = 0;

        // Count modules and their completion
        foreach ($formation->getModules() as $module) {
            $totalItems++;
            if ($this->isModuleCompleted($progress, $module)) {
                $completedItems++;
            }

            // Count chapters
            foreach ($module->getChapters() as $chapter) {
                $totalItems++;
                if ($this->isChapterCompleted($progress, $chapter)) {
                    $completedItems++;
                }

                // Count courses and their content
                foreach ($chapter->getCourses() as $course) {
                    $totalItems++;
                    if ($this->isCourseCompleted($progress, $course)) {
                        $completedItems++;
                    }

                    // Count exercises in this course
                    foreach ($course->getExercises() as $exercise) {
                        $totalItems++;
                        if ($this->isExerciseCompleted($progress, $exercise)) {
                            $completedItems++;
                        }
                    }

                    // Count QCMs in this course
                    foreach ($course->getQcms() as $qcm) {
                        $totalItems++;
                        if ($this->isQCMCompleted($progress, $qcm)) {
                            $completedItems++;
                        }
                    }
                }
            }
        }

        $overallProgress = $totalItems > 0 ? ($completedItems / $totalItems) * 100 : 0.0;
        $progress->setCompletionPercentage($overallProgress);

        return $overallProgress;
    }

    /**
     * Check for milestone achievements.
     */
    public function checkMilestones(StudentEnrollment $enrollment): array
    {
        $progress = $enrollment->getProgress();
        if (!$progress) {
            return [];
        }

        $achievedMilestones = [];
        $currentMilestones = $this->getMilestones($progress);

        // Check for first course completion
        if (!isset($currentMilestones['first_course_completed']) && $this->hasCompletedFirstCourse($progress)) {
            $achievedMilestones['first_course_completed'] = self::MILESTONES['first_course_completed'];
        }

        // Check for first exercise submission
        if (!isset($currentMilestones['first_exercise_submitted']) && $this->hasSubmittedFirstExercise($progress)) {
            $achievedMilestones['first_exercise_submitted'] = self::MILESTONES['first_exercise_submitted'];
        }

        // Check for first QCM passed
        if (!isset($currentMilestones['first_qcm_passed']) && $this->hasPassedFirstQCM($progress)) {
            $achievedMilestones['first_qcm_passed'] = self::MILESTONES['first_qcm_passed'];
        }

        // Check for module completion
        if ($this->hasCompletedNewModule($progress, $currentMilestones)) {
            $achievedMilestones['module_completed'] = self::MILESTONES['module_completed'];
        }

        // Check progress milestones
        $overallProgress = $progress->getCompletionPercentage();
        if ($overallProgress >= 50 && !isset($currentMilestones['formation_halfway'])) {
            $achievedMilestones['formation_halfway'] = self::MILESTONES['formation_halfway'];
        }

        if ($overallProgress >= 75 && !isset($currentMilestones['formation_three_quarters'])) {
            $achievedMilestones['formation_three_quarters'] = self::MILESTONES['formation_three_quarters'];
        }

        // Check engagement milestones
        if ($progress->getEngagementScore() >= 80 && !isset($currentMilestones['high_engagement'])) {
            $achievedMilestones['high_engagement'] = self::MILESTONES['high_engagement'];
        }

        // Save new milestones
        if (!empty($achievedMilestones)) {
            $this->saveMilestones($progress, array_merge($currentMilestones, $achievedMilestones));
            $progress->setLastRiskAssessment(new DateTime()); // Update milestone tracking
        }

        return $achievedMilestones;
    }

    /**
     * Generate comprehensive progress report.
     */
    public function generateProgressReport(StudentEnrollment $enrollment): array
    {
        $progress = $enrollment->getProgress();
        if (!$progress) {
            return [];
        }

        $formation = $enrollment->getFormation();
        $student = $enrollment->getStudent();

        return [
            'student' => [
                'id' => $student?->getId(),
                'name' => $student?->getFullName(),
                'email' => $student?->getEmail()
            ],
            'formation' => [
                'id' => $formation?->getId(),
                'title' => $formation?->getTitle(),
                'level' => $formation?->getLevel()
            ],
            'enrollment' => [
                'id' => $enrollment->getId(),
                'status' => $enrollment->getStatus(),
                'enrolledAt' => $enrollment->getEnrolledAt()?->format('Y-m-d H:i:s'),
                'completedAt' => $enrollment->getCompletedAt()?->format('Y-m-d H:i:s')
            ],
            'progress' => [
                'completion_percentage' => $progress->getCompletionPercentage(),
                'engagement_score' => $progress->getEngagementScore(),
                'total_time_spent' => $progress->getTotalTimeSpent(),
                'login_count' => $progress->getLoginCount(),
                'last_activity' => $progress->getLastActivity()?->format('Y-m-d H:i:s'),
                'risk_score' => $progress->getRiskScore(),
                'at_risk_of_dropout' => $progress->isAtRiskOfDropout()
            ],
            'content_progress' => [
                'courses' => $this->getCourseProgressSummary($progress),
                'exercises' => $this->getExerciseProgressSummary($progress),
                'qcms' => $this->getQCMProgressSummary($progress)
            ],
            'milestones' => $this->getMilestones($progress),
            'time_tracking' => $this->getTimeSpentTracking($progress),
            'learning_analytics' => [
                'average_session_duration' => $progress->getAverageSessionDuration(),
                'engagement_trend' => $this->calculateEngagementTrend($progress),
                'learning_velocity' => $this->calculateLearningVelocity($progress),
                'difficulty_areas' => $progress->getDifficultySignals()
            ]
        ];
    }

    /**
     * Create StudentProgress for enrollment if it doesn't exist.
     */
    private function createProgressForEnrollment(StudentEnrollment $enrollment): StudentProgress
    {
        $progress = new StudentProgress();
        $progress->setStudent($enrollment->getStudent());
        $progress->setFormation($enrollment->getFormation());
        
        $enrollment->setProgress($progress);
        
        $this->entityManager->persist($progress);
        return $progress;
    }

    /**
     * Get exercise progress data from StudentProgress.
     */
    private function getExerciseProgress(StudentProgress $progress): array
    {
        return $progress->getExerciseProgress();
    }

    /**
     * Set exercise progress data in StudentProgress.
     */
    private function setExerciseProgress(StudentProgress $progress, array $exerciseProgress): void
    {
        $progress->setExerciseProgress($exerciseProgress);
    }

    /**
     * Get QCM progress data from StudentProgress.
     */
    private function getQCMProgress(StudentProgress $progress): array
    {
        return $progress->getQCMProgress();
    }

    /**
     * Set QCM progress data in StudentProgress.
     */
    private function setQCMProgress(StudentProgress $progress, array $qcmProgress): void
    {
        $progress->setQCMProgress($qcmProgress);
    }

    /**
     * Get time spent tracking data.
     */
    private function getTimeSpentTracking(StudentProgress $progress): array
    {
        return $progress->getTimeSpentTracking();
    }

    /**
     * Set time spent tracking data.
     */
    private function setTimeSpentTracking(StudentProgress $progress, array $timeTracking): void
    {
        $progress->setTimeSpentTracking($timeTracking);
    }

    /**
     * Get milestones data.
     */
    private function getMilestones(StudentProgress $progress): array
    {
        return $progress->getMilestones();
    }

    /**
     * Save milestones data.
     */
    private function saveMilestones(StudentProgress $progress, array $milestones): void
    {
        $progress->setMilestones($milestones);
    }

    /**
     * Get content type string from object.
     */
    private function getContentType(object $content): string
    {
        return match (true) {
            $content instanceof Course => 'course',
            $content instanceof Exercise => 'exercise',
            $content instanceof QCM => 'qcm',
            default => 'unknown'
        };
    }

    /**
     * Update chapter progress based on course completions.
     */
    private function updateChapterProgress(StudentProgress $progress, ?\App\Entity\Training\Chapter $chapter): void
    {
        if (!$chapter) {
            return;
        }

        $chapterProgress = $progress->getChapterProgress();
        $chapterId = $chapter->getId();
        
        $totalCourses = $chapter->getCourses()->count();
        $completedCourses = 0;

        foreach ($chapter->getCourses() as $course) {
            if ($this->isCourseCompleted($progress, $course)) {
                $completedCourses++;
            }
        }

        $percentage = $totalCourses > 0 ? ($completedCourses / $totalCourses) * 100 : 0;
        
        if (!isset($chapterProgress[$chapterId])) {
            $chapterProgress[$chapterId] = [];
        }
        
        $chapterProgress[$chapterId]['percentage'] = $percentage;
        $chapterProgress[$chapterId]['completed'] = $percentage >= 100;
        
        if ($percentage >= 100) {
            $chapterProgress[$chapterId]['completedAt'] = (new DateTime())->format('Y-m-d H:i:s');
        }

        $progress->setChapterProgress($chapterProgress);
    }

    /**
     * Update module progress based on chapter completions.
     */
    private function updateModuleProgress(StudentProgress $progress, ?\App\Entity\Training\Module $module): void
    {
        if (!$module) {
            return;
        }

        $moduleProgress = $progress->getModuleProgress();
        $moduleId = $module->getId();
        
        $totalChapters = $module->getChapters()->count();
        $completedChapters = 0;

        foreach ($module->getChapters() as $chapter) {
            if ($this->isChapterCompleted($progress, $chapter)) {
                $completedChapters++;
            }
        }

        $percentage = $totalChapters > 0 ? ($completedChapters / $totalChapters) * 100 : 0;
        
        if (!isset($moduleProgress[$moduleId])) {
            $moduleProgress[$moduleId] = [];
        }
        
        $moduleProgress[$moduleId]['percentage'] = $percentage;
        $moduleProgress[$moduleId]['completed'] = $percentage >= 100;
        
        if ($percentage >= 100) {
            $moduleProgress[$moduleId]['completedAt'] = (new DateTime())->format('Y-m-d H:i:s');
        }

        $progress->setModuleProgress($moduleProgress);
    }

    /**
     * Update overall formation progress.
     */
    private function updateOverallProgress(StudentProgress $progress): void
    {
        $moduleProgress = $progress->getModuleProgress();
        $totalModules = count($moduleProgress);
        $completedModules = 0;

        foreach ($moduleProgress as $module) {
            if ($module['completed'] ?? false) {
                $completedModules++;
            }
        }

        $percentage = $totalModules > 0 ? ($completedModules / $totalModules) * 100 : 0;
        $progress->setCompletionPercentage($percentage);

        if ($percentage >= 100 && !$progress->getCompletedAt()) {
            $progress->setCompletedAt(new DateTime());
        }
    }

    /**
     * Check if a course is completed.
     */
    private function isCourseCompleted(StudentProgress $progress, Course $course): bool
    {
        $courseProgress = $progress->getCourseProgress();
        return $courseProgress[$course->getId()]['completed'] ?? false;
    }

    /**
     * Check if a chapter is completed.
     */
    private function isChapterCompleted(StudentProgress $progress, \App\Entity\Training\Chapter $chapter): bool
    {
        $chapterProgress = $progress->getChapterProgress();
        return $chapterProgress[$chapter->getId()]['completed'] ?? false;
    }

    /**
     * Check if a module is completed.
     */
    private function isModuleCompleted(StudentProgress $progress, \App\Entity\Training\Module $module): bool
    {
        $moduleProgress = $progress->getModuleProgress();
        return $moduleProgress[$module->getId()]['completed'] ?? false;
    }

    /**
     * Check if an exercise is completed.
     */
    private function isExerciseCompleted(StudentProgress $progress, Exercise $exercise): bool
    {
        $exerciseProgress = $this->getExerciseProgress($progress);
        return $exerciseProgress[$exercise->getId()]['submitted'] ?? false;
    }

    /**
     * Check if a QCM is completed (passed).
     */
    private function isQCMCompleted(StudentProgress $progress, QCM $qcm): bool
    {
        $qcmProgress = $this->getQCMProgress($progress);
        return $qcmProgress[$qcm->getId()]['passed'] ?? false;
    }

    /**
     * Check if student has completed their first course.
     */
    private function hasCompletedFirstCourse(StudentProgress $progress): bool
    {
        $courseProgress = $progress->getCourseProgress();
        foreach ($courseProgress as $course) {
            if ($course['completed'] ?? false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if student has submitted their first exercise.
     */
    private function hasSubmittedFirstExercise(StudentProgress $progress): bool
    {
        $exerciseProgress = $this->getExerciseProgress($progress);
        foreach ($exerciseProgress as $exercise) {
            if ($exercise['submitted'] ?? false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if student has passed their first QCM.
     */
    private function hasPassedFirstQCM(StudentProgress $progress): bool
    {
        $qcmProgress = $this->getQCMProgress($progress);
        foreach ($qcmProgress as $qcm) {
            if ($qcm['passed'] ?? false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if student has completed a new module.
     */
    private function hasCompletedNewModule(StudentProgress $progress, array $currentMilestones): bool
    {
        $moduleProgress = $progress->getModuleProgress();
        $completedModules = 0;
        
        foreach ($moduleProgress as $module) {
            if ($module['completed'] ?? false) {
                $completedModules++;
            }
        }

        // Check if we have more completed modules than recorded in milestones
        $previousCompletedModules = $currentMilestones['completed_modules'] ?? 0;
        return $completedModules > $previousCompletedModules;
    }

    /**
     * Get course progress summary.
     */
    private function getCourseProgressSummary(StudentProgress $progress): array
    {
        $courseProgress = $progress->getCourseProgress();
        $summary = [
            'total' => count($courseProgress),
            'completed' => 0,
            'in_progress' => 0,
            'not_started' => 0
        ];

        foreach ($courseProgress as $course) {
            if ($course['completed'] ?? false) {
                $summary['completed']++;
            } elseif ($course['viewed'] ?? false) {
                $summary['in_progress']++;
            } else {
                $summary['not_started']++;
            }
        }

        return $summary;
    }

    /**
     * Get exercise progress summary.
     */
    private function getExerciseProgressSummary(StudentProgress $progress): array
    {
        $exerciseProgress = $this->getExerciseProgress($progress);
        $summary = [
            'total' => count($exerciseProgress),
            'submitted' => 0,
            'attempted' => 0,
            'not_started' => 0
        ];

        foreach ($exerciseProgress as $exercise) {
            if ($exercise['submitted'] ?? false) {
                $summary['submitted']++;
            } elseif ($exercise['attempted'] ?? false) {
                $summary['attempted']++;
            } else {
                $summary['not_started']++;
            }
        }

        return $summary;
    }

    /**
     * Get QCM progress summary.
     */
    private function getQCMProgressSummary(StudentProgress $progress): array
    {
        $qcmProgress = $this->getQCMProgress($progress);
        $summary = [
            'total' => count($qcmProgress),
            'passed' => 0,
            'failed' => 0,
            'not_attempted' => 0,
            'average_score' => 0
        ];

        $totalScore = 0;
        $attemptedCount = 0;

        foreach ($qcmProgress as $qcm) {
            if ($qcm['passed'] ?? false) {
                $summary['passed']++;
            } elseif (!empty($qcm['attempts'])) {
                $summary['failed']++;
            } else {
                $summary['not_attempted']++;
            }

            if (!empty($qcm['attempts'])) {
                $attemptedCount++;
                $totalScore += $qcm['bestScore'] ?? 0;
            }
        }

        $summary['average_score'] = $attemptedCount > 0 ? round($totalScore / $attemptedCount, 1) : 0;

        return $summary;
    }

    /**
     * Calculate engagement trend.
     */
    private function calculateEngagementTrend(StudentProgress $progress): string
    {
        // This would ideally use historical data
        // For now, return based on current engagement score
        $engagementScore = $progress->getEngagementScore();
        
        if ($engagementScore >= 80) {
            return 'high';
        } elseif ($engagementScore >= 60) {
            return 'moderate';
        } else {
            return 'low';
        }
    }

    /**
     * Calculate learning velocity.
     */
    private function calculateLearningVelocity(StudentProgress $progress): float
    {
        $startedAt = $progress->getStartedAt();
        $completionPercentage = $progress->getCompletionPercentage();
        
        if (!$startedAt || $completionPercentage <= 0) {
            return 0.0;
        }

        $daysSinceStart = max(1, (new DateTime())->diff($startedAt)->days);
        return round($completionPercentage / $daysSinceStart, 2);
    }
}
