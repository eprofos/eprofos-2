<?php

namespace App\Service\User;

use App\Entity\User\Mentor;
use App\Repository\User\MentorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Mentor Authentication Service
 * 
 * Handles mentor-specific authentication logic including login,
 * password validation, account verification, and security checks.
 */
class MentorAuthenticationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MentorRepository $mentorRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private TokenStorageInterface $tokenStorage,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private MentorService $mentorService
    ) {
    }

    /**
     * Authenticate mentor with email and password
     */
    public function authenticateMentor(string $email, string $password): ?Mentor
    {
        try {
            $this->logger->info('Attempting mentor authentication', [
                'email' => $email
            ]);

            // Find mentor by email
            $mentor = $this->mentorRepository->findByEmail($email);
            
            if (!$mentor) {
                $this->logger->warning('Mentor not found during authentication', [
                    'email' => $email
                ]);
                return null;
            }

            // Verify password
            if (!$this->passwordHasher->isPasswordValid($mentor, $password)) {
                $this->logger->warning('Invalid password during mentor authentication', [
                    'mentor_id' => $mentor->getId(),
                    'email' => $email
                ]);
                return null;
            }

            // Check if mentor is active
            if (!$mentor->isActive()) {
                $this->logger->warning('Inactive mentor attempted login', [
                    'mentor_id' => $mentor->getId(),
                    'email' => $email
                ]);
                throw new AuthenticationException('Votre compte mentor est désactivé. Contactez l\'administration.');
            }

            // Update last login timestamp
            $mentor->updateLastLogin();
            $this->entityManager->flush();

            $this->logger->info('Mentor authentication successful', [
                'mentor_id' => $mentor->getId(),
                'email' => $email
            ]);

            return $mentor;

        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Error during mentor authentication', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            
            throw new AuthenticationException('Erreur lors de l\'authentification. Veuillez réessayer.');
        }
    }

    /**
     * Create new mentor account
     */
    public function createMentorAccount(array $mentorData, string $password): Mentor
    {
        try {
            $this->logger->info('Creating new mentor account', [
                'email' => $mentorData['email'],
                'company' => $mentorData['companyName'] ?? 'Unknown'
            ]);

            // Validate mentor data
            $mentor = new Mentor();
            $mentor->setEmail($mentorData['email']);
            $mentor->setFirstName($mentorData['firstName']);
            $mentor->setLastName($mentorData['lastName']);
            $mentor->setPhone($mentorData['phone'] ?? null);
            $mentor->setPosition($mentorData['position']);
            $mentor->setCompanyName($mentorData['companyName']);
            $mentor->setCompanySiret($mentorData['companySiret']);
            $mentor->setExpertiseDomains($mentorData['expertiseDomains'] ?? []);
            $mentor->setExperienceYears($mentorData['experienceYears']);
            $mentor->setEducationLevel($mentorData['educationLevel']);

            // Validate data
            $errors = $this->mentorService->validateMentorData($mentor);
            if (!empty($errors)) {
                throw new \InvalidArgumentException('Données invalides: ' . implode(', ', $errors));
            }

            // Hash password
            $hashedPassword = $this->passwordHasher->hashPassword($mentor, $password);
            $mentor->setPassword($hashedPassword);

            // Generate email verification token
            $mentor->generateEmailVerificationToken();

            // Persist mentor
            $this->entityManager->persist($mentor);
            $this->entityManager->flush();

            $this->logger->info('Mentor account created successfully', [
                'mentor_id' => $mentor->getId(),
                'email' => $mentor->getEmail()
            ]);

            // Send welcome and verification emails
            $this->mentorService->sendWelcomeEmail($mentor);
            $this->mentorService->sendEmailVerification($mentor);
            $this->mentorService->sendAdminNotificationForNewMentor($mentor);

            return $mentor;

        } catch (\Exception $e) {
            $this->logger->error('Error creating mentor account', [
                'email' => $mentorData['email'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Verify mentor email with token
     */
    public function verifyEmail(string $token): ?Mentor
    {
        try {
            $this->logger->info('Attempting email verification for mentor', [
                'token' => substr($token, 0, 8) . '...'
            ]);

            $mentor = $this->mentorRepository->findByEmailVerificationToken($token);
            
            if (!$mentor) {
                $this->logger->warning('Invalid email verification token', [
                    'token' => substr($token, 0, 8) . '...'
                ]);
                return null;
            }

            // Verify email
            $mentor->verifyEmail();
            $this->entityManager->flush();

            $this->logger->info('Email verification successful', [
                'mentor_id' => $mentor->getId(),
                'email' => $mentor->getEmail()
            ]);

            return $mentor;

        } catch (\Exception $e) {
            $this->logger->error('Error during email verification', [
                'token' => substr($token, 0, 8) . '...',
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Initiate password reset for mentor
     */
    public function initiatePasswordReset(string $email): bool
    {
        try {
            $this->logger->info('Initiating password reset for mentor', [
                'email' => $email
            ]);

            $mentor = $this->mentorRepository->findByEmail($email);
            
            if (!$mentor) {
                $this->logger->warning('Password reset requested for non-existent mentor', [
                    'email' => $email
                ]);
                // Don't reveal if email exists or not
                return true;
            }

            if (!$mentor->isActive()) {
                $this->logger->warning('Password reset requested for inactive mentor', [
                    'mentor_id' => $mentor->getId(),
                    'email' => $email
                ]);
                return false;
            }

            // Send password reset email
            $success = $this->mentorService->sendPasswordResetEmail($mentor);

            $this->logger->info('Password reset initiated', [
                'mentor_id' => $mentor->getId(),
                'email' => $email,
                'success' => $success
            ]);

            return $success;

        } catch (\Exception $e) {
            $this->logger->error('Error initiating password reset', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Reset password with token
     */
    public function resetPassword(string $token, string $newPassword): ?Mentor
    {
        try {
            $this->logger->info('Attempting password reset with token', [
                'token' => substr($token, 0, 8) . '...'
            ]);

            $mentor = $this->mentorRepository->findByPasswordResetToken($token);
            
            if (!$mentor) {
                $this->logger->warning('Invalid or expired password reset token', [
                    'token' => substr($token, 0, 8) . '...'
                ]);
                return null;
            }

            // Validate new password
            if (strlen($newPassword) < 8) {
                throw new \InvalidArgumentException('Le mot de passe doit contenir au moins 8 caractères.');
            }

            // Hash new password
            $hashedPassword = $this->passwordHasher->hashPassword($mentor, $newPassword);
            $mentor->setPassword($hashedPassword);

            // Clear reset token
            $mentor->clearPasswordResetToken();
            
            $this->entityManager->flush();

            $this->logger->info('Password reset successful', [
                'mentor_id' => $mentor->getId(),
                'email' => $mentor->getEmail()
            ]);

            return $mentor;

        } catch (\Exception $e) {
            $this->logger->error('Error during password reset', [
                'token' => substr($token, 0, 8) . '...',
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Change mentor password
     */
    public function changePassword(Mentor $mentor, string $currentPassword, string $newPassword): bool
    {
        try {
            $this->logger->info('Changing password for mentor', [
                'mentor_id' => $mentor->getId()
            ]);

            // Verify current password
            if (!$this->passwordHasher->isPasswordValid($mentor, $currentPassword)) {
                $this->logger->warning('Invalid current password during password change', [
                    'mentor_id' => $mentor->getId()
                ]);
                throw new \InvalidArgumentException('Mot de passe actuel incorrect.');
            }

            // Validate new password
            if (strlen($newPassword) < 8) {
                throw new \InvalidArgumentException('Le nouveau mot de passe doit contenir au moins 8 caractères.');
            }

            // Check if new password is different from current
            if ($this->passwordHasher->isPasswordValid($mentor, $newPassword)) {
                throw new \InvalidArgumentException('Le nouveau mot de passe doit être différent de l\'actuel.');
            }

            // Hash new password
            $hashedPassword = $this->passwordHasher->hashPassword($mentor, $newPassword);
            $mentor->setPassword($hashedPassword);
            
            $this->entityManager->flush();

            $this->logger->info('Password changed successfully', [
                'mentor_id' => $mentor->getId()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Error changing password', [
                'mentor_id' => $mentor->getId(),
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Log in mentor programmatically
     */
    public function loginMentor(Mentor $mentor, Request $request): void
    {
        try {
            $this->logger->info('Logging in mentor programmatically', [
                'mentor_id' => $mentor->getId()
            ]);

            // Create authentication token
            $token = new UsernamePasswordToken(
                $mentor,
                'mentor', // Firewall name
                $mentor->getRoles()
            );

            // Set token in storage
            $this->tokenStorage->setToken($token);

            // Dispatch login event
            $event = new InteractiveLoginEvent($request, $token);
            $this->eventDispatcher->dispatch($event);

            // Update last login
            $mentor->updateLastLogin();
            $this->entityManager->flush();

            $this->logger->info('Mentor logged in successfully', [
                'mentor_id' => $mentor->getId()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error during programmatic login', [
                'mentor_id' => $mentor->getId(),
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Check if mentor account is properly set up
     */
    public function isAccountSetupComplete(Mentor $mentor): bool
    {
        return $mentor->isEmailVerified() 
            && $mentor->isActive()
            && !empty($mentor->getExpertiseDomains())
            && $mentor->getExperienceYears() !== null
            && !empty($mentor->getEducationLevel())
            && !empty($mentor->getPosition())
            && !empty($mentor->getCompanyName())
            && !empty($mentor->getCompanySiret());
    }

    /**
     * Get account setup completion percentage
     */
    public function getAccountSetupCompletion(Mentor $mentor): array
    {
        $checks = [
            'email_verified' => $mentor->isEmailVerified(),
            'personal_info' => !empty($mentor->getFirstName()) && !empty($mentor->getLastName()),
            'contact_info' => !empty($mentor->getPhone()),
            'company_info' => !empty($mentor->getCompanyName()) && !empty($mentor->getCompanySiret()),
            'position_info' => !empty($mentor->getPosition()),
            'expertise_domains' => !empty($mentor->getExpertiseDomains()),
            'experience_info' => $mentor->getExperienceYears() !== null,
            'education_info' => !empty($mentor->getEducationLevel()),
        ];

        $completed = array_sum($checks);
        $total = count($checks);
        $percentage = $total > 0 ? round(($completed / $total) * 100) : 0;

        return [
            'checks' => $checks,
            'completed' => $completed,
            'total' => $total,
            'percentage' => $percentage,
            'is_complete' => $percentage === 100
        ];
    }

    /**
     * Deactivate mentor account
     */
    public function deactivateMentor(Mentor $mentor, string $reason = ''): bool
    {
        try {
            $this->logger->info('Deactivating mentor account', [
                'mentor_id' => $mentor->getId(),
                'reason' => $reason
            ]);

            $mentor->setIsActive(false);
            $this->entityManager->flush();

            $this->logger->info('Mentor account deactivated', [
                'mentor_id' => $mentor->getId()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Error deactivating mentor account', [
                'mentor_id' => $mentor->getId(),
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Reactivate mentor account
     */
    public function reactivateMentor(Mentor $mentor): bool
    {
        try {
            $this->logger->info('Reactivating mentor account', [
                'mentor_id' => $mentor->getId()
            ]);

            $mentor->setIsActive(true);
            $this->entityManager->flush();

            $this->logger->info('Mentor account reactivated', [
                'mentor_id' => $mentor->getId()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Error reactivating mentor account', [
                'mentor_id' => $mentor->getId(),
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Generate secure random password for mentor
     */
    public function generateSecurePassword(): string
    {
        return $this->mentorService->generateRandomPassword(12);
    }

    /**
     * Check for security vulnerabilities in mentor account
     */
    public function performSecurityCheck(Mentor $mentor): array
    {
        $issues = [];

        // Check if email is verified
        if (!$mentor->isEmailVerified()) {
            $issues[] = 'email_not_verified';
        }

        // Check last login activity
        $lastLogin = $mentor->getLastLoginAt();
        if (!$lastLogin || $lastLogin < new \DateTimeImmutable('-90 days')) {
            $issues[] = 'inactive_account';
        }

        // Check if password reset token is expired but not cleared
        if ($mentor->getPasswordResetToken() && 
            $mentor->getPasswordResetTokenExpiresAt() < new \DateTimeImmutable()) {
            $issues[] = 'expired_reset_token';
        }

        // Check for incomplete profile
        if (!$this->isAccountSetupComplete($mentor)) {
            $issues[] = 'incomplete_profile';
        }

        return $issues;
    }

    /**
     * Generate credentials for a new mentor
     */
    public function generateCredentials(Mentor $mentor): array
    {
        $password = $this->generateSecurePassword();
        $hashedPassword = $this->passwordHasher->hashPassword($mentor, $password);
        
        $mentor->setPassword($hashedPassword);
        $mentor->generateEmailVerificationToken();
        
        return [
            'email' => $mentor->getEmail(),
            'password' => $password,
            'verification_token' => $mentor->getEmailVerificationToken(),
        ];
    }

    /**
     * Reset credentials for an existing mentor
     */
    public function resetCredentials(Mentor $mentor): array
    {
        $password = $this->generateSecurePassword();
        $hashedPassword = $this->passwordHasher->hashPassword($mentor, $password);
        
        $mentor->setPassword($hashedPassword);
        $mentor->setEmailVerified(false);
        $mentor->generateEmailVerificationToken();
        
        $this->entityManager->flush();
        
        return [
            'email' => $mentor->getEmail(),
            'password' => $password,
            'verification_token' => $mentor->getEmailVerificationToken(),
        ];
    }
}
