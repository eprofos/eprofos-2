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
        $studentId = $enrollment->getStudent()?->getId();
        $courseId = $course->getId();
        $enrollmentId = $enrollment->getId();

        $this->logger->info('Starting course view recording', [
            'method' => 'recordCourseView',
            'student_id' => $studentId,
            'course_id' => $courseId,
            'enrollment_id' => $enrollmentId,
            'course_title' => $course->getTitle()
        ]);

        try {
            $progress = $enrollment->getProgress();
            if (!$progress) {
                $this->logger->info('No existing progress found, creating new progress record', [
                    'student_id' => $studentId,
                    'enrollment_id' => $enrollmentId
                ]);
                $progress = $this->createProgressForEnrollment($enrollment);
            }

            $courseProgress = $progress->getCourseProgress();
            $now = new DateTime();

            $this->logger->debug('Current course progress state', [
                'student_id' => $studentId,
                'course_id' => $courseId,
                'existing_progress' => isset($courseProgress[$courseId]),
                'current_view_count' => $courseProgress[$courseId]['viewCount'] ?? 0
            ]);

            // Initialize or update course progress
            if (!isset($courseProgress[$courseId])) {
                $this->logger->debug('Initializing new course progress entry', [
                    'student_id' => $studentId,
                    'course_id' => $courseId
                ]);
                $courseProgress[$courseId] = [
                    'viewed' => false,
                    'completed' => false,
                    'timeSpent' => 0,
                    'viewCount' => 0,
                    'firstViewedAt' => null,
                    'lastAccessed' => null
                ];
            }

            $previousViewCount = $courseProgress[$courseId]['viewCount'];
            $courseProgress[$courseId]['viewed'] = true;
            $courseProgress[$courseId]['viewCount']++;
            $courseProgress[$courseId]['lastAccessed'] = $now->format('Y-m-d H:i:s');

            if (!$courseProgress[$courseId]['firstViewedAt']) {
                $courseProgress[$courseId]['firstViewedAt'] = $now->format('Y-m-d H:i:s');
                $this->logger->info('First time viewing this course', [
                    'student_id' => $studentId,
                    'course_id' => $courseId,
                    'first_viewed_at' => $courseProgress[$courseId]['firstViewedAt']
                ]);
            }

            $progress->setCourseProgress($courseProgress);
            $progress->updateActivity();

            $this->logger->debug('Persisting course progress updates', [
                'student_id' => $studentId,
                'course_id' => $courseId,
                'view_count_updated' => $previousViewCount . ' -> ' . $courseProgress[$courseId]['viewCount']
            ]);

            $this->entityManager->persist($progress);
            $this->entityManager->flush();

            $this->logger->info('Course view recorded successfully', [
                'student_id' => $studentId,
                'course_id' => $courseId,
                'enrollment_id' => $enrollmentId,
                'view_count' => $courseProgress[$courseId]['viewCount'],
                'is_first_view' => $previousViewCount === 0
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to record course view', [
                'method' => 'recordCourseView',
                'student_id' => $studentId,
                'course_id' => $courseId,
                'enrollment_id' => $enrollmentId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Record when a student completes a course.
     */
    public function recordCourseCompletion(StudentEnrollment $enrollment, Course $course): void
    {
        $studentId = $enrollment->getStudent()?->getId();
        $courseId = $course->getId();
        $enrollmentId = $enrollment->getId();

        $this->logger->info('Starting course completion recording', [
            'method' => 'recordCourseCompletion',
            'student_id' => $studentId,
            'course_id' => $courseId,
            'enrollment_id' => $enrollmentId,
            'course_title' => $course->getTitle(),
            'chapter_id' => $course->getChapter()?->getId(),
            'module_id' => $course->getChapter()?->getModule()?->getId()
        ]);

        try {
            $progress = $enrollment->getProgress();
            if (!$progress) {
                $this->logger->info('No existing progress found, creating new progress record', [
                    'student_id' => $studentId,
                    'enrollment_id' => $enrollmentId
                ]);
                $progress = $this->createProgressForEnrollment($enrollment);
            }

            $courseProgress = $progress->getCourseProgress();
            $now = new DateTime();

            $this->logger->debug('Current course completion state', [
                'student_id' => $studentId,
                'course_id' => $courseId,
                'was_already_completed' => $courseProgress[$courseId]['completed'] ?? false,
                'was_viewed' => $courseProgress[$courseId]['viewed'] ?? false
            ]);

            // Update course completion
            if (!isset($courseProgress[$courseId])) {
                $this->logger->info('Course not yet viewed, recording view first', [
                    'student_id' => $studentId,
                    'course_id' => $courseId
                ]);
                $this->recordCourseView($enrollment, $course);
                $courseProgress = $progress->getCourseProgress();
            }

            $wasAlreadyCompleted = $courseProgress[$courseId]['completed'] ?? false;
            $courseProgress[$courseId]['completed'] = true;
            $courseProgress[$courseId]['completedAt'] = $now->format('Y-m-d H:i:s');

            $progress->setCourseProgress($courseProgress);

            if (!$wasAlreadyCompleted) {
                $this->logger->info('Course completed for the first time, updating parent progress', [
                    'student_id' => $studentId,
                    'course_id' => $courseId,
                    'chapter_id' => $course->getChapter()?->getId(),
                    'module_id' => $course->getChapter()?->getModule()?->getId()
                ]);

                $this->updateChapterProgress($progress, $course->getChapter());
                $this->updateModuleProgress($progress, $course->getChapter()?->getModule());
                $this->updateOverallProgress($progress);
            } else {
                $this->logger->debug('Course was already completed, skipping parent updates', [
                    'student_id' => $studentId,
                    'course_id' => $courseId
                ]);
            }

            $this->logger->debug('Persisting course completion updates', [
                'student_id' => $studentId,
                'course_id' => $courseId,
                'completion_percentage' => $progress->getCompletionPercentage()
            ]);

            $this->entityManager->persist($progress);
            $this->entityManager->flush();

            // Check for milestones
            $this->logger->debug('Checking for milestone achievements', [
                'student_id' => $studentId,
                'course_id' => $courseId
            ]);
            $milestones = $this->checkMilestones($enrollment);
            
            $this->logger->info('Course completion recorded successfully', [
                'student_id' => $studentId,
                'course_id' => $courseId,
                'enrollment_id' => $enrollmentId,
                'was_already_completed' => $wasAlreadyCompleted,
                'milestones_achieved' => array_keys($milestones),
                'overall_progress' => $progress->getCompletionPercentage()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to record course completion', [
                'method' => 'recordCourseCompletion',
                'student_id' => $studentId,
                'course_id' => $courseId,
                'enrollment_id' => $enrollmentId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Record exercise submission with detailed tracking.
     */
    public function recordExerciseSubmission(StudentEnrollment $enrollment, Exercise $exercise, array $submission): void
    {
        $studentId = $enrollment->getStudent()?->getId();
        $exerciseId = $exercise->getId();
        $enrollmentId = $enrollment->getId();

        $this->logger->info('Starting exercise submission recording', [
            'method' => 'recordExerciseSubmission',
            'student_id' => $studentId,
            'exercise_id' => $exerciseId,
            'enrollment_id' => $enrollmentId,
            'exercise_title' => $exercise->getTitle(),
            'submission_size' => count($submission),
            'course_id' => $exercise->getCourse()?->getId()
        ]);

        try {
            $progress = $enrollment->getProgress();
            if (!$progress) {
                $this->logger->info('No existing progress found, creating new progress record', [
                    'student_id' => $studentId,
                    'enrollment_id' => $enrollmentId
                ]);
                $progress = $this->createProgressForEnrollment($enrollment);
            }

            $exerciseProgress = $this->getExerciseProgress($progress);
            $now = new DateTime();

            $this->logger->debug('Current exercise submission state', [
                'student_id' => $studentId,
                'exercise_id' => $exerciseId,
                'previous_attempts' => $exerciseProgress[$exerciseId]['attempts'] ?? 0,
                'was_previously_submitted' => $exerciseProgress[$exerciseId]['submitted'] ?? false,
                'submission_keys' => array_keys($submission)
            ]);

            // Initialize or update exercise progress
            if (!isset($exerciseProgress[$exerciseId])) {
                $this->logger->debug('Initializing new exercise progress entry', [
                    'student_id' => $studentId,
                    'exercise_id' => $exerciseId
                ]);
                $exerciseProgress[$exerciseId] = [
                    'attempted' => false,
                    'submitted' => false,
                    'attempts' => 0,
                    'timeSpent' => 0,
                    'submissions' => []
                ];
            }

            $previousAttempts = $exerciseProgress[$exerciseId]['attempts'];
            $exerciseProgress[$exerciseId]['attempted'] = true;
            $exerciseProgress[$exerciseId]['submitted'] = true;
            $exerciseProgress[$exerciseId]['attempts']++;

            $submissionData = [
                'submittedAt' => $now->format('Y-m-d H:i:s'),
                'content' => $submission,
                'status' => 'submitted'
            ];

            $exerciseProgress[$exerciseId]['submissions'][] = $submissionData;

            $this->logger->debug('Exercise submission data prepared', [
                'student_id' => $studentId,
                'exercise_id' => $exerciseId,
                'attempt_number' => $exerciseProgress[$exerciseId]['attempts'],
                'total_submissions' => count($exerciseProgress[$exerciseId]['submissions'])
            ]);

            $this->setExerciseProgress($progress, $exerciseProgress);
            $progress->updateActivity();

            $this->logger->debug('Persisting exercise submission updates', [
                'student_id' => $studentId,
                'exercise_id' => $exerciseId,
                'attempts_updated' => $previousAttempts . ' -> ' . $exerciseProgress[$exerciseId]['attempts']
            ]);

            $this->entityManager->persist($progress);
            $this->entityManager->flush();

            // Check for milestones
            $this->logger->debug('Checking for milestone achievements after exercise submission', [
                'student_id' => $studentId,
                'exercise_id' => $exerciseId
            ]);
            $milestones = $this->checkMilestones($enrollment);

            $this->logger->info('Exercise submission recorded successfully', [
                'student_id' => $studentId,
                'exercise_id' => $exerciseId,
                'enrollment_id' => $enrollmentId,
                'attempt_number' => $exerciseProgress[$exerciseId]['attempts'],
                'milestones_achieved' => array_keys($milestones),
                'is_first_submission' => $previousAttempts === 0
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to record exercise submission', [
                'method' => 'recordExerciseSubmission',
                'student_id' => $studentId,
                'exercise_id' => $exerciseId,
                'enrollment_id' => $enrollmentId,
                'submission_data' => $submission,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Record QCM attempt with score and detailed answers.
     */
    public function recordQCMAttempt(StudentEnrollment $enrollment, QCM $qcm, array $answers, int $score): void
    {
        $studentId = $enrollment->getStudent()?->getId();
        $qcmId = $qcm->getId();
        $enrollmentId = $enrollment->getId();
        $passingScore = $qcm->getPassingScore() ?? 60;

        $this->logger->info('Starting QCM attempt recording', [
            'method' => 'recordQCMAttempt',
            'student_id' => $studentId,
            'qcm_id' => $qcmId,
            'enrollment_id' => $enrollmentId,
            'qcm_title' => $qcm->getTitle(),
            'score' => $score,
            'passing_score' => $passingScore,
            'passed' => $score >= $passingScore,
            'answers_count' => count($answers),
            'course_id' => $qcm->getCourse()?->getId()
        ]);

        try {
            $progress = $enrollment->getProgress();
            if (!$progress) {
                $this->logger->info('No existing progress found, creating new progress record', [
                    'student_id' => $studentId,
                    'enrollment_id' => $enrollmentId
                ]);
                $progress = $this->createProgressForEnrollment($enrollment);
            }

            $qcmProgress = $this->getQCMProgress($progress);
            $now = new DateTime();
            $maxScore = 100; // Assuming 100 as max score, adjust as needed

            $this->logger->debug('Current QCM attempt state', [
                'student_id' => $studentId,
                'qcm_id' => $qcmId,
                'previous_attempts' => count($qcmProgress[$qcmId]['attempts'] ?? []),
                'previous_best_score' => $qcmProgress[$qcmId]['bestScore'] ?? 0,
                'was_previously_passed' => $qcmProgress[$qcmId]['passed'] ?? false
            ]);

            // Initialize or update QCM progress
            if (!isset($qcmProgress[$qcmId])) {
                $this->logger->debug('Initializing new QCM progress entry', [
                    'student_id' => $studentId,
                    'qcm_id' => $qcmId
                ]);
                $qcmProgress[$qcmId] = [
                    'attempts' => [],
                    'bestScore' => 0,
                    'passed' => false,
                    'timeSpent' => 0
                ];
            }

            $previousBestScore = $qcmProgress[$qcmId]['bestScore'];
            $previousPassed = $qcmProgress[$qcmId]['passed'];
            $attemptNumber = count($qcmProgress[$qcmId]['attempts']) + 1;

            // Add new attempt
            $attempt = [
                'attemptAt' => $now->format('Y-m-d H:i:s'),
                'score' => $score,
                'maxScore' => $maxScore,
                'timeSpent' => 0, // This should be calculated from actual time tracking
                'answers' => $answers,
                'passed' => $score >= $passingScore
            ];

            $qcmProgress[$qcmId]['attempts'][] = $attempt;
            $qcmProgress[$qcmId]['bestScore'] = max($qcmProgress[$qcmId]['bestScore'], $score);
            $qcmProgress[$qcmId]['passed'] = $qcmProgress[$qcmId]['bestScore'] >= $passingScore;

            $scoreImproved = $score > $previousBestScore;
            $justPassed = !$previousPassed && $qcmProgress[$qcmId]['passed'];

            $this->logger->debug('QCM attempt data prepared', [
                'student_id' => $studentId,
                'qcm_id' => $qcmId,
                'attempt_number' => $attemptNumber,
                'score_improved' => $scoreImproved,
                'just_passed' => $justPassed,
                'new_best_score' => $qcmProgress[$qcmId]['bestScore']
            ]);

            $this->setQCMProgress($progress, $qcmProgress);
            $progress->updateActivity();

            $this->logger->debug('Persisting QCM attempt updates', [
                'student_id' => $studentId,
                'qcm_id' => $qcmId,
                'best_score_updated' => $previousBestScore . ' -> ' . $qcmProgress[$qcmId]['bestScore']
            ]);

            $this->entityManager->persist($progress);
            $this->entityManager->flush();

            // Check for milestones
            $this->logger->debug('Checking for milestone achievements after QCM attempt', [
                'student_id' => $studentId,
                'qcm_id' => $qcmId,
                'just_passed' => $justPassed
            ]);
            $milestones = $this->checkMilestones($enrollment);

            $this->logger->info('QCM attempt recorded successfully', [
                'student_id' => $studentId,
                'qcm_id' => $qcmId,
                'enrollment_id' => $enrollmentId,
                'attempt_number' => $attemptNumber,
                'score' => $score,
                'passed' => $attempt['passed'],
                'score_improved' => $scoreImproved,
                'just_passed' => $justPassed,
                'milestones_achieved' => array_keys($milestones),
                'best_score' => $qcmProgress[$qcmId]['bestScore']
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to record QCM attempt', [
                'method' => 'recordQCMAttempt',
                'student_id' => $studentId,
                'qcm_id' => $qcmId,
                'enrollment_id' => $enrollmentId,
                'score' => $score,
                'answers_data' => $answers,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Update time spent on specific content.
     */
    public function updateTimeSpent(StudentEnrollment $enrollment, object $content, int $seconds): void
    {
        $studentId = $enrollment->getStudent()?->getId();
        $enrollmentId = $enrollment->getId();
        $contentType = $this->getContentType($content);
        $contentId = $content->getId();

        $this->logger->info('Starting time spent update', [
            'method' => 'updateTimeSpent',
            'student_id' => $studentId,
            'enrollment_id' => $enrollmentId,
            'content_type' => $contentType,
            'content_id' => $contentId,
            'seconds_to_add' => $seconds,
            'minutes_to_add' => ceil($seconds / 60)
        ]);

        try {
            if ($seconds < 0) {
                $this->logger->warning('Negative time value provided, skipping update', [
                    'student_id' => $studentId,
                    'content_type' => $contentType,
                    'content_id' => $contentId,
                    'seconds' => $seconds
                ]);
                return;
            }

            $progress = $enrollment->getProgress();
            if (!$progress) {
                $this->logger->info('No existing progress found, creating new progress record', [
                    'student_id' => $studentId,
                    'enrollment_id' => $enrollmentId
                ]);
                $progress = $this->createProgressForEnrollment($enrollment);
            }

            $previousTotalTime = $progress->getTotalTimeSpent();
            $minutesToAdd = (int) ceil($seconds / 60);

            // Add to total time spent
            $progress->addTimeSpent($minutesToAdd);

            // Update specific content time tracking
            $timeTracking = $this->getTimeSpentTracking($progress);

            $this->logger->debug('Current time tracking state', [
                'student_id' => $studentId,
                'content_type' => $contentType,
                'content_id' => $contentId,
                'previous_total_time' => $previousTotalTime,
                'previous_content_time' => $timeTracking[$contentType][$contentId] ?? 0
            ]);

            if (!isset($timeTracking[$contentType])) {
                $timeTracking[$contentType] = [];
            }

            if (!isset($timeTracking[$contentType][$contentId])) {
                $timeTracking[$contentType][$contentId] = 0;
            }

            $previousContentTime = $timeTracking[$contentType][$contentId];
            $timeTracking[$contentType][$contentId] += $seconds;
            $this->setTimeSpentTracking($progress, $timeTracking);

            $this->logger->debug('Persisting time tracking updates', [
                'student_id' => $studentId,
                'content_type' => $contentType,
                'content_id' => $contentId,
                'total_time_updated' => $previousTotalTime . ' -> ' . $progress->getTotalTimeSpent(),
                'content_time_updated' => $previousContentTime . ' -> ' . $timeTracking[$contentType][$contentId]
            ]);

            $this->entityManager->persist($progress);
            $this->entityManager->flush();

            $this->logger->info('Time spent updated successfully', [
                'student_id' => $studentId,
                'enrollment_id' => $enrollmentId,
                'content_type' => $contentType,
                'content_id' => $contentId,
                'seconds_added' => $seconds,
                'minutes_added' => $minutesToAdd,
                'new_total_time' => $progress->getTotalTimeSpent(),
                'new_content_time' => $timeTracking[$contentType][$contentId]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to update time spent', [
                'method' => 'updateTimeSpent',
                'student_id' => $studentId,
                'enrollment_id' => $enrollmentId,
                'content_type' => $contentType,
                'content_id' => $contentId,
                'seconds' => $seconds,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Calculate overall progress from all completed content.
     */
    public function calculateOverallProgress(StudentEnrollment $enrollment): float
    {
        $studentId = $enrollment->getStudent()?->getId();
        $enrollmentId = $enrollment->getId();
        $formationId = $enrollment->getFormation()?->getId();

        $this->logger->info('Starting overall progress calculation', [
            'method' => 'calculateOverallProgress',
            'student_id' => $studentId,
            'enrollment_id' => $enrollmentId,
            'formation_id' => $formationId
        ]);

        try {
            $progress = $enrollment->getProgress();
            if (!$progress) {
                $this->logger->debug('No progress found, returning 0%', [
                    'student_id' => $studentId,
                    'enrollment_id' => $enrollmentId
                ]);
                return 0.0;
            }

            $formation = $enrollment->getFormation();
            if (!$formation) {
                $this->logger->warning('No formation found for enrollment', [
                    'student_id' => $studentId,
                    'enrollment_id' => $enrollmentId
                ]);
                return 0.0;
            }

            $totalItems = 0;
            $completedItems = 0;
            $moduleCount = 0;
            $chapterCount = 0;
            $courseCount = 0;
            $exerciseCount = 0;
            $qcmCount = 0;

            $this->logger->debug('Starting content enumeration', [
                'student_id' => $studentId,
                'formation_id' => $formationId,
                'formation_title' => $formation->getTitle()
            ]);

            // Count modules and their completion
            foreach ($formation->getModules() as $module) {
                $moduleCount++;
                $totalItems++;
                if ($this->isModuleCompleted($progress, $module)) {
                    $completedItems++;
                }

                // Count chapters
                foreach ($module->getChapters() as $chapter) {
                    $chapterCount++;
                    $totalItems++;
                    if ($this->isChapterCompleted($progress, $chapter)) {
                        $completedItems++;
                    }

                    // Count courses and their content
                    foreach ($chapter->getCourses() as $course) {
                        $courseCount++;
                        $totalItems++;
                        if ($this->isCourseCompleted($progress, $course)) {
                            $completedItems++;
                        }

                        // Count exercises in this course
                        foreach ($course->getExercises() as $exercise) {
                            $exerciseCount++;
                            $totalItems++;
                            if ($this->isExerciseCompleted($progress, $exercise)) {
                                $completedItems++;
                            }
                        }

                        // Count QCMs in this course
                        foreach ($course->getQcms() as $qcm) {
                            $qcmCount++;
                            $totalItems++;
                            if ($this->isQCMCompleted($progress, $qcm)) {
                                $completedItems++;
                            }
                        }
                    }
                }
            }

            $overallProgress = $totalItems > 0 ? ($completedItems / $totalItems) * 100 : 0.0;
            $previousProgress = $progress->getCompletionPercentage();
            $progress->setCompletionPercentage($overallProgress);

            $this->logger->info('Overall progress calculation completed', [
                'student_id' => $studentId,
                'enrollment_id' => $enrollmentId,
                'formation_id' => $formationId,
                'content_breakdown' => [
                    'modules' => $moduleCount,
                    'chapters' => $chapterCount,
                    'courses' => $courseCount,
                    'exercises' => $exerciseCount,
                    'qcms' => $qcmCount
                ],
                'progress_calculation' => [
                    'total_items' => $totalItems,
                    'completed_items' => $completedItems,
                    'previous_progress' => $previousProgress,
                    'new_progress' => $overallProgress,
                    'progress_increased' => $overallProgress > $previousProgress
                ]
            ]);

            return $overallProgress;

        } catch (\Exception $e) {
            $this->logger->error('Failed to calculate overall progress', [
                'method' => 'calculateOverallProgress',
                'student_id' => $studentId,
                'enrollment_id' => $enrollmentId,
                'formation_id' => $formationId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Check for milestone achievements.
     */
    public function checkMilestones(StudentEnrollment $enrollment): array
    {
        $studentId = $enrollment->getStudent()?->getId();
        $enrollmentId = $enrollment->getId();

        $this->logger->info('Starting milestone check', [
            'method' => 'checkMilestones',
            'student_id' => $studentId,
            'enrollment_id' => $enrollmentId
        ]);

        try {
            $progress = $enrollment->getProgress();
            if (!$progress) {
                $this->logger->debug('No progress found, skipping milestone check', [
                    'student_id' => $studentId,
                    'enrollment_id' => $enrollmentId
                ]);
                return [];
            }

            $achievedMilestones = [];
            $currentMilestones = $this->getMilestones($progress);
            $overallProgress = $progress->getCompletionPercentage();
            $engagementScore = $progress->getEngagementScore();

            $this->logger->debug('Current milestone state', [
                'student_id' => $studentId,
                'existing_milestones' => array_keys($currentMilestones),
                'overall_progress' => $overallProgress,
                'engagement_score' => $engagementScore
            ]);

            // Check for first course completion
            if (!isset($currentMilestones['first_course_completed']) && $this->hasCompletedFirstCourse($progress)) {
                $achievedMilestones['first_course_completed'] = self::MILESTONES['first_course_completed'];
                $this->logger->info('First course completion milestone achieved', [
                    'student_id' => $studentId
                ]);
            }

            // Check for first exercise submission
            if (!isset($currentMilestones['first_exercise_submitted']) && $this->hasSubmittedFirstExercise($progress)) {
                $achievedMilestones['first_exercise_submitted'] = self::MILESTONES['first_exercise_submitted'];
                $this->logger->info('First exercise submission milestone achieved', [
                    'student_id' => $studentId
                ]);
            }

            // Check for first QCM passed
            if (!isset($currentMilestones['first_qcm_passed']) && $this->hasPassedFirstQCM($progress)) {
                $achievedMilestones['first_qcm_passed'] = self::MILESTONES['first_qcm_passed'];
                $this->logger->info('First QCM passed milestone achieved', [
                    'student_id' => $studentId
                ]);
            }

            // Check for module completion
            if ($this->hasCompletedNewModule($progress, $currentMilestones)) {
                $achievedMilestones['module_completed'] = self::MILESTONES['module_completed'];
                $this->logger->info('Module completion milestone achieved', [
                    'student_id' => $studentId
                ]);
            }

            // Check progress milestones
            if ($overallProgress >= 50 && !isset($currentMilestones['formation_halfway'])) {
                $achievedMilestones['formation_halfway'] = self::MILESTONES['formation_halfway'];
                $this->logger->info('Formation halfway milestone achieved', [
                    'student_id' => $studentId,
                    'progress' => $overallProgress
                ]);
            }

            if ($overallProgress >= 75 && !isset($currentMilestones['formation_three_quarters'])) {
                $achievedMilestones['formation_three_quarters'] = self::MILESTONES['formation_three_quarters'];
                $this->logger->info('Formation three quarters milestone achieved', [
                    'student_id' => $studentId,
                    'progress' => $overallProgress
                ]);
            }

            // Check engagement milestones
            if ($engagementScore >= 80 && !isset($currentMilestones['high_engagement'])) {
                $achievedMilestones['high_engagement'] = self::MILESTONES['high_engagement'];
                $this->logger->info('High engagement milestone achieved', [
                    'student_id' => $studentId,
                    'engagement_score' => $engagementScore
                ]);
            }

            // Save new milestones
            if (!empty($achievedMilestones)) {
                $newMilestones = array_merge($currentMilestones, $achievedMilestones);
                $this->saveMilestones($progress, $newMilestones);
                $progress->setLastRiskAssessment(new DateTime()); // Update milestone tracking

                $this->logger->info('New milestones saved', [
                    'student_id' => $studentId,
                    'new_milestones' => array_keys($achievedMilestones),
                    'total_milestones' => count($newMilestones)
                ]);
            } else {
                $this->logger->debug('No new milestones achieved', [
                    'student_id' => $studentId
                ]);
            }

            $this->logger->info('Milestone check completed', [
                'student_id' => $studentId,
                'enrollment_id' => $enrollmentId,
                'milestones_achieved' => array_keys($achievedMilestones),
                'total_points_earned' => array_sum(array_column($achievedMilestones, 'points'))
            ]);

            return $achievedMilestones;

        } catch (\Exception $e) {
            $this->logger->error('Failed to check milestones', [
                'method' => 'checkMilestones',
                'student_id' => $studentId,
                'enrollment_id' => $enrollmentId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Generate comprehensive progress report.
     */
    public function generateProgressReport(StudentEnrollment $enrollment): array
    {
        $studentId = $enrollment->getStudent()?->getId();
        $enrollmentId = $enrollment->getId();

        $this->logger->info('Starting progress report generation', [
            'method' => 'generateProgressReport',
            'student_id' => $studentId,
            'enrollment_id' => $enrollmentId
        ]);

        try {
            $progress = $enrollment->getProgress();
            if (!$progress) {
                $this->logger->warning('No progress found for enrollment, returning empty report', [
                    'student_id' => $studentId,
                    'enrollment_id' => $enrollmentId
                ]);
                return [];
            }

            $formation = $enrollment->getFormation();
            $student = $enrollment->getStudent();

            $this->logger->debug('Gathering report data', [
                'student_id' => $studentId,
                'formation_id' => $formation?->getId(),
                'formation_title' => $formation?->getTitle(),
                'enrollment_status' => $enrollment->getStatus()
            ]);

            $report = [
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

            $this->logger->info('Progress report generated successfully', [
                'student_id' => $studentId,
                'enrollment_id' => $enrollmentId,
                'report_sections' => array_keys($report),
                'completion_percentage' => $report['progress']['completion_percentage'],
                'engagement_score' => $report['progress']['engagement_score'],
                'milestones_count' => count($report['milestones']),
                'at_risk' => $report['progress']['at_risk_of_dropout']
            ]);

            return $report;

        } catch (\Exception $e) {
            $this->logger->error('Failed to generate progress report', [
                'method' => 'generateProgressReport',
                'student_id' => $studentId,
                'enrollment_id' => $enrollmentId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Create StudentProgress for enrollment if it doesn't exist.
     */
    private function createProgressForEnrollment(StudentEnrollment $enrollment): StudentProgress
    {
        $studentId = $enrollment->getStudent()?->getId();
        $enrollmentId = $enrollment->getId();
        $formationId = $enrollment->getFormation()?->getId();

        $this->logger->info('Creating new StudentProgress record', [
            'method' => 'createProgressForEnrollment',
            'student_id' => $studentId,
            'enrollment_id' => $enrollmentId,
            'formation_id' => $formationId
        ]);

        try {
            $progress = new StudentProgress();
            $progress->setStudent($enrollment->getStudent());
            $progress->setFormation($enrollment->getFormation());
            
            $enrollment->setProgress($progress);
            
            $this->entityManager->persist($progress);

            $this->logger->info('StudentProgress record created successfully', [
                'student_id' => $studentId,
                'enrollment_id' => $enrollmentId,
                'formation_id' => $formationId,
                'progress_id' => $progress->getId()
            ]);

            return $progress;

        } catch (\Exception $e) {
            $this->logger->error('Failed to create StudentProgress record', [
                'method' => 'createProgressForEnrollment',
                'student_id' => $studentId,
                'enrollment_id' => $enrollmentId,
                'formation_id' => $formationId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
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
            $this->logger->debug('No chapter provided, skipping chapter progress update');
            return;
        }

        $chapterId = $chapter->getId();
        $studentId = $progress->getStudent()?->getId();

        $this->logger->debug('Starting chapter progress update', [
            'method' => 'updateChapterProgress',
            'student_id' => $studentId,
            'chapter_id' => $chapterId,
            'chapter_title' => $chapter->getTitle()
        ]);

        try {
            $chapterProgress = $progress->getChapterProgress();
            
            $totalCourses = $chapter->getCourses()->count();
            $completedCourses = 0;

            foreach ($chapter->getCourses() as $course) {
                if ($this->isCourseCompleted($progress, $course)) {
                    $completedCourses++;
                }
            }

            $percentage = $totalCourses > 0 ? ($completedCourses / $totalCourses) * 100 : 0;
            $wasCompleted = $chapterProgress[$chapterId]['completed'] ?? false;
            
            if (!isset($chapterProgress[$chapterId])) {
                $chapterProgress[$chapterId] = [];
            }
            
            $chapterProgress[$chapterId]['percentage'] = $percentage;
            $chapterProgress[$chapterId]['completed'] = $percentage >= 100;
            
            if ($percentage >= 100 && !$wasCompleted) {
                $chapterProgress[$chapterId]['completedAt'] = (new DateTime())->format('Y-m-d H:i:s');
                $this->logger->info('Chapter completed', [
                    'student_id' => $studentId,
                    'chapter_id' => $chapterId,
                    'chapter_title' => $chapter->getTitle(),
                    'completion_percentage' => $percentage
                ]);
            }

            $progress->setChapterProgress($chapterProgress);

            $this->logger->debug('Chapter progress updated', [
                'student_id' => $studentId,
                'chapter_id' => $chapterId,
                'total_courses' => $totalCourses,
                'completed_courses' => $completedCourses,
                'percentage' => $percentage,
                'just_completed' => $percentage >= 100 && !$wasCompleted
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to update chapter progress', [
                'method' => 'updateChapterProgress',
                'student_id' => $studentId,
                'chapter_id' => $chapterId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);
            throw $e;
        }
    }

    /**
     * Update module progress based on chapter completions.
     */
    private function updateModuleProgress(StudentProgress $progress, ?\App\Entity\Training\Module $module): void
    {
        if (!$module) {
            $this->logger->debug('No module provided, skipping module progress update');
            return;
        }

        $moduleId = $module->getId();
        $studentId = $progress->getStudent()?->getId();

        $this->logger->debug('Starting module progress update', [
            'method' => 'updateModuleProgress',
            'student_id' => $studentId,
            'module_id' => $moduleId,
            'module_title' => $module->getTitle()
        ]);

        try {
            $moduleProgress = $progress->getModuleProgress();
            
            $totalChapters = $module->getChapters()->count();
            $completedChapters = 0;

            foreach ($module->getChapters() as $chapter) {
                if ($this->isChapterCompleted($progress, $chapter)) {
                    $completedChapters++;
                }
            }

            $percentage = $totalChapters > 0 ? ($completedChapters / $totalChapters) * 100 : 0;
            $wasCompleted = $moduleProgress[$moduleId]['completed'] ?? false;
            
            if (!isset($moduleProgress[$moduleId])) {
                $moduleProgress[$moduleId] = [];
            }
            
            $moduleProgress[$moduleId]['percentage'] = $percentage;
            $moduleProgress[$moduleId]['completed'] = $percentage >= 100;
            
            if ($percentage >= 100 && !$wasCompleted) {
                $moduleProgress[$moduleId]['completedAt'] = (new DateTime())->format('Y-m-d H:i:s');
                $this->logger->info('Module completed', [
                    'student_id' => $studentId,
                    'module_id' => $moduleId,
                    'module_title' => $module->getTitle(),
                    'completion_percentage' => $percentage
                ]);
            }

            $progress->setModuleProgress($moduleProgress);

            $this->logger->debug('Module progress updated', [
                'student_id' => $studentId,
                'module_id' => $moduleId,
                'total_chapters' => $totalChapters,
                'completed_chapters' => $completedChapters,
                'percentage' => $percentage,
                'just_completed' => $percentage >= 100 && !$wasCompleted
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to update module progress', [
                'method' => 'updateModuleProgress',
                'student_id' => $studentId,
                'module_id' => $moduleId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);
            throw $e;
        }
    }

    /**
     * Update overall formation progress.
     */
    private function updateOverallProgress(StudentProgress $progress): void
    {
        $studentId = $progress->getStudent()?->getId();

        $this->logger->debug('Starting overall progress update', [
            'method' => 'updateOverallProgress',
            'student_id' => $studentId
        ]);

        try {
            $moduleProgress = $progress->getModuleProgress();
            $totalModules = count($moduleProgress);
            $completedModules = 0;
            $previousProgress = $progress->getCompletionPercentage();
            $previousCompletedAt = $progress->getCompletedAt();

            foreach ($moduleProgress as $moduleId => $module) {
                if ($module['completed'] ?? false) {
                    $completedModules++;
                }
            }

            $percentage = $totalModules > 0 ? ($completedModules / $totalModules) * 100 : 0;
            $progress->setCompletionPercentage($percentage);

            if ($percentage >= 100 && !$previousCompletedAt) {
                $progress->setCompletedAt(new DateTime());
                $this->logger->info('Formation completed', [
                    'student_id' => $studentId,
                    'completion_percentage' => $percentage,
                    'completed_at' => $progress->getCompletedAt()?->format('Y-m-d H:i:s')
                ]);
            }

            $this->logger->debug('Overall progress updated', [
                'student_id' => $studentId,
                'total_modules' => $totalModules,
                'completed_modules' => $completedModules,
                'previous_percentage' => $previousProgress,
                'new_percentage' => $percentage,
                'formation_completed' => $percentage >= 100,
                'just_completed' => $percentage >= 100 && !$previousCompletedAt
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to update overall progress', [
                'method' => 'updateOverallProgress',
                'student_id' => $studentId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);
            throw $e;
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
