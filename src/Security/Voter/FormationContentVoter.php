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
        private readonly ContentAccessService $contentAccessService
    ) {
    }

    /**
     * Determines if the attribute and subject are supported by this voter.
     */
    protected function supports(string $attribute, mixed $subject): bool
    {
        // Check if the attribute is supported
        if (!in_array($attribute, [self::VIEW, self::INTERACT], true)) {
            return false;
        }

        // Check if the subject is a supported content type
        foreach (self::SUPPORTED_CLASSES as $supportedClass) {
            if ($subject instanceof $supportedClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * Performs the authorization logic.
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // If user is not authenticated, deny access
        if (!$user instanceof UserInterface) {
            $this->logAccessAttempt($subject, $attribute, null, false, 'User not authenticated');
            return false;
        }

        // Admin users have full access to all content
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $this->logAccessAttempt($subject, $attribute, $user, true, 'Admin override');
            return true;
        }

        // Only students can access training content
        if (!$user instanceof Student) {
            $this->logAccessAttempt($subject, $attribute, $user, false, 'User is not a student');
            return false;
        }

        // Check specific permissions based on attribute and content type
        $hasAccess = match ($attribute) {
            self::VIEW => $this->canView($user, $subject),
            self::INTERACT => $this->canInteract($user, $subject),
            default => false
        };

        // Log the access attempt for Qualiopi compliance
        $this->contentAccessService->logContentAccess($user, $subject, $hasAccess);

        return $hasAccess;
    }

    /**
     * Check if student can view the content.
     */
    private function canView(Student $student, object $content): bool
    {
        return match (true) {
            $content instanceof Formation => $this->contentAccessService->canAccessFormation($student, $content),
            $content instanceof Module => $this->contentAccessService->canAccessModule($student, $content),
            $content instanceof Chapter => $this->contentAccessService->canAccessChapter($student, $content),
            $content instanceof Course => $this->contentAccessService->canAccessCourse($student, $content),
            $content instanceof Exercise => $this->contentAccessService->canAccessExercise($student, $content),
            $content instanceof QCM => $this->contentAccessService->canAccessQCM($student, $content),
            default => false
        };
    }

    /**
     * Check if student can interact with the content.
     * 
     * For most content types, interaction has the same requirements as viewing.
     * This method allows for future differentiation if needed.
     */
    private function canInteract(Student $student, object $content): bool
    {
        // For now, interaction requires the same permissions as viewing
        return $this->canView($student, $content);
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
        // This method could be enhanced to provide detailed logging
        // For now, the main logging is handled by ContentAccessService
    }
}
