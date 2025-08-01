<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Training\Chapter;
use App\Entity\Training\Course;
use App\Entity\Training\Exercise;
use App\Entity\Training\Formation;
use App\Entity\Training\Module;
use App\Entity\Training\QCM;
use App\Entity\User\Student;
use App\Service\Security\ContentAccessService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * FormationContentVoter handles authorization for training content access.
 *
 * This voter implements the core access control logic for the Student Content
 * Access System, ensuring only enrolled students can access training materials.
 * Critical for security and Qualiopi compliance.
 */
class FormationContentVoter extends Voter
{
    // Supported attributes
    public const VIEW = 'view';
    public const INTERACT = 'interact';

    // Supported content types
    private const SUPPORTED_CLASSES = [
        Formation::class,
        Module::class,
        Chapter::class,
        Course::class,
        Exercise::class,
        QCM::class,
    ];

    public function __construct(
        private readonly ContentAccessService $contentAccessService,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Determines if the attribute and subject are supported by this voter.
     */
    protected function supports(string $attribute, mixed $subject): bool
    {
        try {
            $this->logger->debug('FormationContentVoter support check initiated', [
                'attribute' => $attribute,
                'subject_class' => $subject ? get_class($subject) : 'null',
                'subject_id' => $this->getContentId($subject),
                'timestamp' => new \DateTimeImmutable(),
            ]);

            // Check if the attribute is supported
            if (!in_array($attribute, [self::VIEW, self::INTERACT], true)) {
                $this->logger->debug('Attribute not supported by FormationContentVoter', [
                    'attribute' => $attribute,
                    'supported_attributes' => [self::VIEW, self::INTERACT],
                    'subject_class' => $subject ? get_class($subject) : 'null',
                ]);
                return false;
            }

            // Check if the subject is a supported content type
            $isSupported = false;
            $subjectClass = null;
            
            if ($subject !== null) {
                $subjectClass = get_class($subject);
                foreach (self::SUPPORTED_CLASSES as $supportedClass) {
                    if ($subject instanceof $supportedClass) {
                        $isSupported = true;
                        break;
                    }
                }
            }

            $this->logger->info('FormationContentVoter support decision', [
                'attribute' => $attribute,
                'subject_class' => $subjectClass,
                'subject_id' => $this->getContentId($subject),
                'is_supported' => $isSupported,
                'supported_classes' => self::SUPPORTED_CLASSES,
                'attribute_supported' => in_array($attribute, [self::VIEW, self::INTERACT], true),
                'timestamp' => new \DateTimeImmutable(),
            ]);

            return $isSupported;

        } catch (\Exception $e) {
            $this->logger->error('Exception in FormationContentVoter supports method', [
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'attribute' => $attribute,
                'subject_class' => $subject ? get_class($subject) : 'null',
                'timestamp' => new \DateTimeImmutable(),
            ]);

            // Default to not supported when exception occurs
            return false;
        }
    }

    /**
     * Performs the authorization logic.
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        try {
            $user = $token->getUser();
            $contentId = $this->getContentId($subject);
            $contentClass = $subject ? get_class($subject) : 'null';

            $this->logger->info('FormationContentVoter authorization check initiated', [
                'attribute' => $attribute,
                'subject_class' => $contentClass,
                'subject_id' => $contentId,
                'user_class' => $user ? get_class($user) : 'null',
                'user_identifier' => $user instanceof UserInterface ? $user->getUserIdentifier() : 'anonymous',
                'user_roles' => $user instanceof UserInterface ? $user->getRoles() : [],
                'timestamp' => new \DateTimeImmutable(),
            ]);

            // If user is not authenticated, deny access
            if (!$user instanceof UserInterface) {
                $this->logger->warning('Access denied - user not authenticated', [
                    'attribute' => $attribute,
                    'subject_class' => $contentClass,
                    'subject_id' => $contentId,
                    'user_type' => $user ? get_class($user) : 'null',
                    'reason' => 'User not authenticated',
                ]);

                $this->logAccessAttempt($subject, $attribute, null, false, 'User not authenticated');
                return false;
            }

            // Admin users have full access to all content
            if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                $this->logger->info('Access granted - admin override', [
                    'attribute' => $attribute,
                    'subject_class' => $contentClass,
                    'subject_id' => $contentId,
                    'user_identifier' => $user->getUserIdentifier(),
                    'user_roles' => $user->getRoles(),
                    'reason' => 'Admin override',
                ]);

                $this->logAccessAttempt($subject, $attribute, $user, true, 'Admin override');
                return true;
            }

            // Only students can access training content
            if (!$user instanceof Student) {
                $this->logger->warning('Access denied - user is not a student', [
                    'attribute' => $attribute,
                    'subject_class' => $contentClass,
                    'subject_id' => $contentId,
                    'user_identifier' => $user->getUserIdentifier(),
                    'user_class' => get_class($user),
                    'user_roles' => $user->getRoles(),
                    'reason' => 'User is not a student',
                ]);

                $this->logAccessAttempt($subject, $attribute, $user, false, 'User is not a student');
                return false;
            }

            $this->logger->debug('Starting permission check for student', [
                'attribute' => $attribute,
                'subject_class' => $contentClass,
                'subject_id' => $contentId,
                'student_id' => $user->getId(),
                'student_email' => $user->getEmail(),
                'student_active' => $user->isActive(),
                'student_verified' => $user->isEmailVerified(),
            ]);

            // Check specific permissions based on attribute and content type
            $hasAccess = match ($attribute) {
                self::VIEW => $this->canView($user, $subject),
                self::INTERACT => $this->canInteract($user, $subject),
                default => false
            };

            $this->logger->info('Authorization decision made', [
                'attribute' => $attribute,
                'subject_class' => $contentClass,
                'subject_id' => $contentId,
                'student_id' => $user->getId(),
                'student_email' => $user->getEmail(),
                'has_access' => $hasAccess,
                'decision_timestamp' => new \DateTimeImmutable(),
            ]);

            // Log the access attempt for Qualiopi compliance
            try {
                $this->contentAccessService->logContentAccess($user, $subject, $hasAccess);
                $this->logger->debug('Content access logged successfully', [
                    'student_id' => $user->getId(),
                    'subject_class' => $contentClass,
                    'subject_id' => $contentId,
                    'access_granted' => $hasAccess,
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to log content access', [
                    'exception_message' => $e->getMessage(),
                    'exception_class' => get_class($e),
                    'student_id' => $user->getId(),
                    'subject_class' => $contentClass,
                    'subject_id' => $contentId,
                    'access_granted' => $hasAccess,
                ]);
            }

            return $hasAccess;

        } catch (\Exception $e) {
            $this->logger->error('Exception in FormationContentVoter voteOnAttribute method', [
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'attribute' => $attribute,
                'subject_class' => $subject ? get_class($subject) : 'null',
                'subject_id' => $this->getContentId($subject),
                'user_identifier' => $token->getUser() instanceof UserInterface ? $token->getUser()->getUserIdentifier() : 'unknown',
                'timestamp' => new \DateTimeImmutable(),
            ]);

            // Default to deny access when exception occurs for security
            $this->logAccessAttempt($subject, $attribute, $token->getUser(), false, 'Exception occurred: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if student can view the content.
     */
    private function canView(Student $student, object $content): bool
    {
        try {
            $contentClass = get_class($content);
            $contentId = $this->getContentId($content);

            $this->logger->debug('Checking view permission for student', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'content_class' => $contentClass,
                'content_id' => $contentId,
                'check_type' => 'view',
                'timestamp' => new \DateTimeImmutable(),
            ]);

            $hasAccess = match (true) {
                $content instanceof Formation => $this->contentAccessService->canAccessFormation($student, $content),
                $content instanceof Module => $this->contentAccessService->canAccessModule($student, $content),
                $content instanceof Chapter => $this->contentAccessService->canAccessChapter($student, $content),
                $content instanceof Course => $this->contentAccessService->canAccessCourse($student, $content),
                $content instanceof Exercise => $this->contentAccessService->canAccessExercise($student, $content),
                $content instanceof QCM => $this->contentAccessService->canAccessQCM($student, $content),
                default => false
            };

            $this->logger->info('View permission check completed', [
                'student_id' => $student->getId(),
                'content_class' => $contentClass,
                'content_id' => $contentId,
                'has_access' => $hasAccess,
                'check_type' => 'view',
                'decision_timestamp' => new \DateTimeImmutable(),
            ]);

            return $hasAccess;

        } catch (\Exception $e) {
            $this->logger->error('Exception in canView method', [
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'student_id' => $student->getId(),
                'content_class' => $content ? get_class($content) : 'null',
                'content_id' => $this->getContentId($content),
                'timestamp' => new \DateTimeImmutable(),
            ]);

            // Default to deny access when exception occurs
            return false;
        }
    }

    /**
     * Check if student can interact with the content.
     * 
     * For most content types, interaction has the same requirements as viewing.
     * This method allows for future differentiation if needed.
     */
    private function canInteract(Student $student, object $content): bool
    {
        try {
            $contentClass = get_class($content);
            $contentId = $this->getContentId($content);

            $this->logger->debug('Checking interact permission for student', [
                'student_id' => $student->getId(),
                'student_email' => $student->getEmail(),
                'content_class' => $contentClass,
                'content_id' => $contentId,
                'check_type' => 'interact',
                'timestamp' => new \DateTimeImmutable(),
            ]);

            // For now, interaction requires the same permissions as viewing
            // This could be enhanced in the future for more granular control
            $hasAccess = $this->canView($student, $content);

            $this->logger->info('Interact permission check completed', [
                'student_id' => $student->getId(),
                'content_class' => $contentClass,
                'content_id' => $contentId,
                'has_access' => $hasAccess,
                'check_type' => 'interact',
                'decision_timestamp' => new \DateTimeImmutable(),
            ]);

            return $hasAccess;

        } catch (\Exception $e) {
            $this->logger->error('Exception in canInteract method', [
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'student_id' => $student->getId(),
                'content_class' => $content ? get_class($content) : 'null',
                'content_id' => $this->getContentId($content),
                'timestamp' => new \DateTimeImmutable(),
            ]);

            // Default to deny access when exception occurs
            return false;
        }
    }

    /**
     * Log access attempt for debugging and compliance.
     */
    private function logAccessAttempt(
        object $content,
        string $attribute,
        ?UserInterface $user,
        bool $granted,
        string $reason
    ): void {
        try {
            $contentClass = $content ? get_class($content) : 'null';
            $contentId = $this->getContentId($content);
            $userId = null;
            $userEmail = null;
            $userRoles = [];
            $userClass = null;

            if ($user instanceof UserInterface) {
                $userEmail = $user->getUserIdentifier();
                $userRoles = $user->getRoles();
                $userClass = get_class($user);
                
                if ($user instanceof Student) {
                    $userId = $user->getId();
                }
            }

            $logLevel = $granted ? 'info' : 'warning';
            $logMessage = $granted ? 'Content access granted' : 'Content access denied';

            $this->logger->log($logLevel, $logMessage, [
                'access_decision' => [
                    'granted' => $granted,
                    'reason' => $reason,
                    'attribute' => $attribute,
                ],
                'content' => [
                    'class' => $contentClass,
                    'id' => $contentId,
                ],
                'user' => [
                    'id' => $userId,
                    'email' => $userEmail,
                    'class' => $userClass,
                    'roles' => $userRoles,
                    'is_authenticated' => $user instanceof UserInterface,
                    'is_student' => $user instanceof Student,
                ],
                'security' => [
                    'voter' => 'FormationContentVoter',
                    'decision_timestamp' => new \DateTimeImmutable(),
                ],
                'compliance' => [
                    'logged_for_qualiopi' => true,
                    'access_type' => $attribute,
                    'security_check' => 'passed',
                ],
            ]);

        } catch (\Exception $e) {
            // Even if logging fails, we should not affect the security decision
            // Log the logging error separately
            $this->logger->error('Failed to log access attempt', [
                'exception_message' => $e->getMessage(),
                'exception_class' => get_class($e),
                'original_access_granted' => $granted,
                'original_reason' => $reason,
                'attribute' => $attribute,
                'content_class' => $content ? get_class($content) : 'null',
                'timestamp' => new \DateTimeImmutable(),
            ]);
        }
    }

    /**
     * Get content ID for logging purposes.
     */
    private function getContentId(mixed $content): ?int
    {
        if (!$content) {
            return null;
        }

        try {
            return match (true) {
                $content instanceof Formation,
                $content instanceof Module,
                $content instanceof Chapter,
                $content instanceof Course,
                $content instanceof Exercise,
                $content instanceof QCM => method_exists($content, 'getId') ? $content->getId() : null,
                default => null
            };
        } catch (\Exception $e) {
            $this->logger->debug('Failed to get content ID', [
                'exception_message' => $e->getMessage(),
                'content_class' => get_class($content),
            ]);
            return null;
        }
    }
}
