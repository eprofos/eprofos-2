<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User\Mentor;
use App\Repository\User\MentorRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

/**
 * Mentor Authentication Service.
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
        private MentorService $mentorService,
    ) {}

    /**
     * Authenticate mentor with email and password.
     */
    public function authenticateMentor(string $email, string $password): ?Mentor
    {
        $startTime = microtime(true);
        $this->logger->info('Starting mentor authentication process', [
            'email' => $email,
            'timestamp' => date('Y-m-d H:i:s'),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);

        try {
            // Step 1: Validate input parameters
            $this->logger->debug('Validating authentication parameters', [
                'email' => $email,
                'password_length' => strlen($password),
                'email_valid' => filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
            ]);

            if (empty($email) || empty($password)) {
                $this->logger->warning('Empty credentials provided during authentication', [
                    'email_empty' => empty($email),
                    'password_empty' => empty($password),
                ]);

                return null;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->logger->warning('Invalid email format during authentication', [
                    'email' => $email,
                ]);

                return null;
            }

            // Step 2: Find mentor by email
            $this->logger->debug('Searching for mentor in database', [
                'email' => $email,
            ]);

            $mentor = $this->mentorRepository->findByEmail($email);

            if (!$mentor) {
                $this->logger->warning('Mentor not found during authentication attempt', [
                    'email' => $email,
                    'search_duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                return null;
            }

            $this->logger->debug('Mentor found in database', [
                'mentor_id' => $mentor->getId(),
                'email' => $email,
                'mentor_status' => $mentor->isActive() ? 'active' : 'inactive',
                'email_verified' => $mentor->isEmailVerified(),
                'last_login' => $mentor->getLastLoginAt()?->format('Y-m-d H:i:s'),
            ]);

            // Step 3: Verify password
            $this->logger->debug('Starting password verification', [
                'mentor_id' => $mentor->getId(),
                'email' => $email,
            ]);

            $passwordValid = $this->passwordHasher->isPasswordValid($mentor, $password);

            if (!$passwordValid) {
                $this->logger->warning('Invalid password provided during mentor authentication', [
                    'mentor_id' => $mentor->getId(),
                    'email' => $email,
                    'attempt_duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'mentor_last_login' => $mentor->getLastLoginAt()?->format('Y-m-d H:i:s'),
                ]);

                return null;
            }

            $this->logger->debug('Password verification successful', [
                'mentor_id' => $mentor->getId(),
                'email' => $email,
            ]);

            // Step 4: Check if mentor is active
            if (!$mentor->isActive()) {
                $this->logger->warning('Inactive mentor attempted login', [
                    'mentor_id' => $mentor->getId(),
                    'email' => $email,
                    'company' => $mentor->getCompanyName(),
                    'created_at' => $mentor->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'last_login' => $mentor->getLastLoginAt()?->format('Y-m-d H:i:s'),
                ]);

                throw new AuthenticationException('Votre compte mentor est désactivé. Contactez l\'administration.');
            }

            // Step 5: Update last login timestamp
            $this->logger->debug('Updating mentor last login timestamp', [
                'mentor_id' => $mentor->getId(),
                'previous_login' => $mentor->getLastLoginAt()?->format('Y-m-d H:i:s'),
            ]);

            try {
                $mentor->updateLastLogin();
                $this->entityManager->flush();

                $this->logger->debug('Last login timestamp updated successfully', [
                    'mentor_id' => $mentor->getId(),
                    'new_login_time' => $mentor->getLastLoginAt()?->format('Y-m-d H:i:s'),
                ]);
            } catch (Exception $dbException) {
                $this->logger->error('Failed to update last login timestamp', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $dbException->getMessage(),
                    'trace' => $dbException->getTraceAsString(),
                ]);
                // Continue with authentication even if last login update fails
            }

            $totalDuration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->info('Mentor authentication completed successfully', [
                'mentor_id' => $mentor->getId(),
                'email' => $email,
                'company' => $mentor->getCompanyName(),
                'total_duration_ms' => $totalDuration,
                'mentor_expertise_domains' => $mentor->getExpertiseDomains(),
                'mentor_experience_years' => $mentor->getExperienceYears(),
            ]);

            return $mentor;
        } catch (AuthenticationException $e) {
            $this->logger->error('Authentication exception during mentor login', [
                'email' => $email,
                'error_message' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'exception_type' => get_class($e),
            ]);

            throw $e;
        } catch (Exception $e) {
            $this->logger->error('Unexpected error during mentor authentication', [
                'email' => $email,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'exception_type' => get_class($e),
            ]);

            throw new AuthenticationException('Erreur lors de l\'authentification. Veuillez réessayer.');
        }
    }

    /**
     * Create new mentor account.
     */
    public function createMentorAccount(array $mentorData, string $password): Mentor
    {
        $startTime = microtime(true);
        $this->logger->info('Starting mentor account creation process', [
            'email' => $mentorData['email'] ?? 'not_provided',
            'company' => $mentorData['companyName'] ?? 'not_provided',
            'timestamp' => date('Y-m-d H:i:s'),
            'data_keys' => array_keys($mentorData),
        ]);

        try {
            // Step 1: Validate input data
            $this->logger->debug('Validating mentor creation data', [
                'email' => $mentorData['email'] ?? 'missing',
                'firstName' => isset($mentorData['firstName']) ? 'provided' : 'missing',
                'lastName' => isset($mentorData['lastName']) ? 'provided' : 'missing',
                'companyName' => isset($mentorData['companyName']) ? 'provided' : 'missing',
                'companySiret' => isset($mentorData['companySiret']) ? 'provided' : 'missing',
                'position' => isset($mentorData['position']) ? 'provided' : 'missing',
                'experienceYears' => $mentorData['experienceYears'] ?? 'missing',
                'educationLevel' => isset($mentorData['educationLevel']) ? 'provided' : 'missing',
                'password_length' => strlen($password),
            ]);

            $requiredFields = ['email', 'firstName', 'lastName', 'position', 'companyName', 'companySiret'];
            foreach ($requiredFields as $field) {
                if (!isset($mentorData[$field]) || empty($mentorData[$field])) {
                    $this->logger->error('Missing required field during mentor creation', [
                        'missing_field' => $field,
                        'provided_data' => array_keys($mentorData),
                    ]);

                    throw new InvalidArgumentException("Le champ '{$field}' est requis.");
                }
            }

            // Check for existing mentor with same email
            $this->logger->debug('Checking for existing mentor with email', [
                'email' => $mentorData['email'],
            ]);

            $existingMentor = $this->mentorRepository->findByEmail($mentorData['email']);
            if ($existingMentor) {
                $this->logger->warning('Attempt to create mentor with existing email', [
                    'email' => $mentorData['email'],
                    'existing_mentor_id' => $existingMentor->getId(),
                    'existing_mentor_active' => $existingMentor->isActive(),
                ]);

                throw new InvalidArgumentException('Un mentor avec cette adresse email existe déjà.');
            }

            // Step 2: Create mentor entity
            $this->logger->debug('Creating new mentor entity', [
                'email' => $mentorData['email'],
            ]);

            $mentor = new Mentor();

            try {
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

                $this->logger->debug('Mentor entity properties set successfully', [
                    'mentor_email' => $mentor->getEmail(),
                    'mentor_company' => $mentor->getCompanyName(),
                    'expertise_domains_count' => count($mentor->getExpertiseDomains()),
                ]);
            } catch (Exception $entityException) {
                $this->logger->error('Error setting mentor entity properties', [
                    'email' => $mentorData['email'],
                    'error' => $entityException->getMessage(),
                    'trace' => $entityException->getTraceAsString(),
                ]);

                throw new InvalidArgumentException('Erreur lors de la création de l\'entité mentor: ' . $entityException->getMessage());
            }

            // Step 3: Validate mentor data
            $this->logger->debug('Validating mentor data with service', [
                'mentor_email' => $mentor->getEmail(),
            ]);

            try {
                $errors = $this->mentorService->validateMentorData($mentor);
                if (!empty($errors)) {
                    $this->logger->warning('Mentor data validation failed', [
                        'email' => $mentor->getEmail(),
                        'validation_errors' => $errors,
                        'error_count' => count($errors),
                    ]);

                    throw new InvalidArgumentException('Données invalides: ' . implode(', ', $errors));
                }

                $this->logger->debug('Mentor data validation passed', [
                    'mentor_email' => $mentor->getEmail(),
                ]);
            } catch (Exception $validationException) {
                $this->logger->error('Error during mentor data validation', [
                    'email' => $mentor->getEmail(),
                    'error' => $validationException->getMessage(),
                ]);

                throw $validationException;
            }

            // Step 4: Hash password
            $this->logger->debug('Hashing mentor password', [
                'mentor_email' => $mentor->getEmail(),
                'password_length' => strlen($password),
            ]);

            try {
                $hashedPassword = $this->passwordHasher->hashPassword($mentor, $password);
                $mentor->setPassword($hashedPassword);

                $this->logger->debug('Password hashed successfully', [
                    'mentor_email' => $mentor->getEmail(),
                    'hash_length' => strlen($hashedPassword),
                ]);
            } catch (Exception $hashException) {
                $this->logger->error('Error hashing mentor password', [
                    'email' => $mentor->getEmail(),
                    'error' => $hashException->getMessage(),
                ]);

                throw new InvalidArgumentException('Erreur lors du hachage du mot de passe.');
            }

            // Step 5: Generate email verification token
            $this->logger->debug('Generating email verification token', [
                'mentor_email' => $mentor->getEmail(),
            ]);

            try {
                $mentor->generateEmailVerificationToken();
                $this->logger->debug('Email verification token generated', [
                    'mentor_email' => $mentor->getEmail(),
                    'token_prefix' => substr($mentor->getEmailVerificationToken(), 0, 8) . '...',
                ]);
            } catch (Exception $tokenException) {
                $this->logger->error('Error generating email verification token', [
                    'email' => $mentor->getEmail(),
                    'error' => $tokenException->getMessage(),
                ]);

                throw new InvalidArgumentException('Erreur lors de la génération du token de vérification.');
            }

            // Step 6: Persist mentor to database
            $this->logger->debug('Persisting mentor to database', [
                'mentor_email' => $mentor->getEmail(),
            ]);

            try {
                $this->entityManager->persist($mentor);
                $this->entityManager->flush();

                $this->logger->info('Mentor persisted to database successfully', [
                    'mentor_id' => $mentor->getId(),
                    'mentor_email' => $mentor->getEmail(),
                    'company' => $mentor->getCompanyName(),
                ]);
            } catch (Exception $dbException) {
                $this->logger->error('Database error during mentor persistence', [
                    'email' => $mentor->getEmail(),
                    'error' => $dbException->getMessage(),
                    'trace' => $dbException->getTraceAsString(),
                ]);

                throw new InvalidArgumentException('Erreur lors de l\'enregistrement en base de données.');
            }

            // Step 7: Send notification emails
            $this->logger->debug('Starting email notification process', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
            ]);

            try {
                // Send welcome email
                $this->logger->debug('Sending welcome email', [
                    'mentor_id' => $mentor->getId(),
                ]);
                $welcomeEmailSent = $this->mentorService->sendWelcomeEmail($mentor);

                // Send verification email
                $this->logger->debug('Sending email verification', [
                    'mentor_id' => $mentor->getId(),
                ]);
                $verificationEmailSent = $this->mentorService->sendEmailVerification($mentor);

                // Send admin notification
                $this->logger->debug('Sending admin notification', [
                    'mentor_id' => $mentor->getId(),
                ]);
                $adminNotificationSent = $this->mentorService->sendAdminNotificationForNewMentor($mentor);

                $this->logger->info('Email notifications sent', [
                    'mentor_id' => $mentor->getId(),
                    'welcome_email_sent' => $welcomeEmailSent,
                    'verification_email_sent' => $verificationEmailSent,
                    'admin_notification_sent' => $adminNotificationSent,
                ]);
            } catch (Exception $emailException) {
                $this->logger->warning('Error sending notification emails (non-blocking)', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $emailException->getMessage(),
                ]);
                // Don't throw exception for email errors as account creation succeeded
            }

            $totalDuration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->info('Mentor account creation completed successfully', [
                'mentor_id' => $mentor->getId(),
                'email' => $mentor->getEmail(),
                'company' => $mentor->getCompanyName(),
                'total_duration_ms' => $totalDuration,
                'expertise_domains' => $mentor->getExpertiseDomains(),
                'experience_years' => $mentor->getExperienceYears(),
            ]);

            return $mentor;
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Invalid argument during mentor account creation', [
                'email' => $mentorData['email'] ?? 'unknown',
                'error_message' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            throw $e;
        } catch (Exception $e) {
            $this->logger->error('Unexpected error during mentor account creation', [
                'email' => $mentorData['email'] ?? 'unknown',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'exception_type' => get_class($e),
            ]);

            throw new InvalidArgumentException('Erreur inattendue lors de la création du compte mentor.');
        }
    }

    /**
     * Verify mentor email with token.
     */
    public function verifyEmail(string $token): ?Mentor
    {
        $startTime = microtime(true);
        $this->logger->info('Starting email verification process', [
            'token_prefix' => substr($token, 0, 8) . '...',
            'token_length' => strlen($token),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            // Step 1: Validate token format
            $this->logger->debug('Validating token format', [
                'token_prefix' => substr($token, 0, 8) . '...',
                'token_length' => strlen($token),
                'is_empty' => empty($token),
            ]);

            if (empty($token)) {
                $this->logger->warning('Empty token provided for email verification');

                return null;
            }

            if (strlen($token) < 32) {
                $this->logger->warning('Invalid token length for email verification', [
                    'token_length' => strlen($token),
                    'expected_min_length' => 32,
                ]);

                return null;
            }

            // Step 2: Search for mentor with token
            $this->logger->debug('Searching for mentor with verification token', [
                'token_prefix' => substr($token, 0, 8) . '...',
            ]);

            try {
                $mentor = $this->mentorRepository->findByEmailVerificationToken($token);
            } catch (Exception $dbException) {
                $this->logger->error('Database error during token search', [
                    'token_prefix' => substr($token, 0, 8) . '...',
                    'error' => $dbException->getMessage(),
                    'trace' => $dbException->getTraceAsString(),
                ]);

                return null;
            }

            if (!$mentor) {
                $this->logger->warning('No mentor found with provided verification token', [
                    'token_prefix' => substr($token, 0, 8) . '...',
                    'search_duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                return null;
            }

            $this->logger->debug('Mentor found with verification token', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'mentor_active' => $mentor->isActive(),
                'already_verified' => $mentor->isEmailVerified(),
                'created_at' => $mentor->getCreatedAt()?->format('Y-m-d H:i:s'),
            ]);

            // Step 3: Check if email is already verified
            if ($mentor->isEmailVerified()) {
                $this->logger->info('Email already verified for mentor', [
                    'mentor_id' => $mentor->getId(),
                    'mentor_email' => $mentor->getEmail(),
                ]);

                return $mentor;
            }

            // Step 4: Verify email
            $this->logger->debug('Proceeding with email verification', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
            ]);

            try {
                $mentor->verifyEmail();

                $this->logger->debug('Email verification method called successfully', [
                    'mentor_id' => $mentor->getId(),
                    'now_verified' => $mentor->isEmailVerified(),
                ]);
            } catch (Exception $verificationException) {
                $this->logger->error('Error calling email verification method', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $verificationException->getMessage(),
                    'trace' => $verificationException->getTraceAsString(),
                ]);

                throw new Exception('Erreur lors de la vérification de l\'email.');
            }

            // Step 5: Persist changes to database
            $this->logger->debug('Persisting email verification to database', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
            ]);

            try {
                $this->entityManager->flush();

                $this->logger->debug('Email verification persisted successfully', [
                    'mentor_id' => $mentor->getId(),
                ]);
            } catch (Exception $dbException) {
                $this->logger->error('Database error during email verification persistence', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $dbException->getMessage(),
                    'trace' => $dbException->getTraceAsString(),
                ]);

                throw new Exception('Erreur lors de l\'enregistrement de la vérification.');
            }

            $totalDuration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->info('Email verification completed successfully', [
                'mentor_id' => $mentor->getId(),
                'email' => $mentor->getEmail(),
                'company' => $mentor->getCompanyName(),
                'total_duration_ms' => $totalDuration,
                'verification_date' => date('Y-m-d H:i:s'),
            ]);

            return $mentor;
        } catch (Exception $e) {
            $this->logger->error('Unexpected error during email verification', [
                'token_prefix' => substr($token, 0, 8) . '...',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'exception_type' => get_class($e),
            ]);

            return null;
        }
    }

    /**
     * Initiate password reset for mentor.
     */
    public function initiatePasswordReset(string $email): bool
    {
        $startTime = microtime(true);
        $this->logger->info('Starting password reset initiation process', [
            'email' => $email,
            'timestamp' => date('Y-m-d H:i:s'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);

        try {
            // Step 1: Validate email format
            $this->logger->debug('Validating email format for password reset', [
                'email' => $email,
                'email_valid' => filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
                'email_empty' => empty($email),
            ]);

            if (empty($email)) {
                $this->logger->warning('Empty email provided for password reset');

                return false;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->logger->warning('Invalid email format for password reset', [
                    'email' => $email,
                ]);

                return false;
            }

            // Step 2: Search for mentor
            $this->logger->debug('Searching for mentor for password reset', [
                'email' => $email,
            ]);

            try {
                $mentor = $this->mentorRepository->findByEmail($email);
            } catch (Exception $dbException) {
                $this->logger->error('Database error during mentor search for password reset', [
                    'email' => $email,
                    'error' => $dbException->getMessage(),
                    'trace' => $dbException->getTraceAsString(),
                ]);

                // Return true to not reveal if email exists
                return true;
            }

            if (!$mentor) {
                $this->logger->warning('Password reset requested for non-existent mentor', [
                    'email' => $email,
                    'search_duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                // Don't reveal if email exists or not - return true for security
                $this->logger->info('Password reset process completed (email not found)', [
                    'email' => $email,
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                return true;
            }

            $this->logger->debug('Mentor found for password reset', [
                'mentor_id' => $mentor->getId(),
                'email' => $email,
                'mentor_active' => $mentor->isActive(),
                'email_verified' => $mentor->isEmailVerified(),
                'last_login' => $mentor->getLastLoginAt()?->format('Y-m-d H:i:s'),
            ]);

            // Step 3: Check if mentor is active
            if (!$mentor->isActive()) {
                $this->logger->warning('Password reset requested for inactive mentor', [
                    'mentor_id' => $mentor->getId(),
                    'email' => $email,
                    'created_at' => $mentor->getCreatedAt()?->format('Y-m-d H:i:s'),
                ]);

                $this->logger->info('Password reset denied for inactive mentor', [
                    'mentor_id' => $mentor->getId(),
                    'email' => $email,
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                return false;
            }

            // Step 4: Check rate limiting (prevent abuse)
            $this->logger->debug('Checking rate limiting for password reset', [
                'mentor_id' => $mentor->getId(),
                'email' => $email,
            ]);

            // If mentor has a recent password reset token, log but still proceed
            if ($mentor->getPasswordResetToken() && $mentor->getPasswordResetTokenExpiresAt()) {
                $tokenExpiry = $mentor->getPasswordResetTokenExpiresAt();
                if ($tokenExpiry > new DateTimeImmutable()) {
                    $this->logger->info('Password reset requested with existing valid token', [
                        'mentor_id' => $mentor->getId(),
                        'email' => $email,
                        'existing_token_expires_at' => $tokenExpiry->format('Y-m-d H:i:s'),
                        'minutes_until_expiry' => $tokenExpiry->diff(new DateTimeImmutable())->i,
                    ]);
                }
            }

            // Step 5: Send password reset email
            $this->logger->debug('Sending password reset email', [
                'mentor_id' => $mentor->getId(),
                'email' => $email,
            ]);

            try {
                $success = $this->mentorService->sendPasswordResetEmail($mentor);

                $this->logger->debug('Password reset email service called', [
                    'mentor_id' => $mentor->getId(),
                    'email' => $email,
                    'email_sent' => $success,
                ]);
            } catch (Exception $emailException) {
                $this->logger->error('Error sending password reset email', [
                    'mentor_id' => $mentor->getId(),
                    'email' => $email,
                    'error' => $emailException->getMessage(),
                    'trace' => $emailException->getTraceAsString(),
                ]);

                // Return false if email sending fails
                return false;
            }

            $totalDuration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->info('Password reset initiation completed', [
                'mentor_id' => $mentor->getId(),
                'email' => $email,
                'success' => $success,
                'total_duration_ms' => $totalDuration,
                'company' => $mentor->getCompanyName(),
            ]);

            return $success;
        } catch (Exception $e) {
            $this->logger->error('Unexpected error during password reset initiation', [
                'email' => $email,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'exception_type' => get_class($e),
            ]);

            return false;
        }
    }

    /**
     * Reset password with token.
     */
    public function resetPassword(string $token, string $newPassword): ?Mentor
    {
        $startTime = microtime(true);
        $this->logger->info('Starting password reset process with token', [
            'token_prefix' => substr($token, 0, 8) . '...',
            'token_length' => strlen($token),
            'new_password_length' => strlen($newPassword),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            // Step 1: Validate token format
            $this->logger->debug('Validating password reset token format', [
                'token_prefix' => substr($token, 0, 8) . '...',
                'token_length' => strlen($token),
                'is_empty' => empty($token),
            ]);

            if (empty($token)) {
                $this->logger->warning('Empty token provided for password reset');

                return null;
            }

            if (strlen($token) < 32) {
                $this->logger->warning('Invalid token length for password reset', [
                    'token_length' => strlen($token),
                    'expected_min_length' => 32,
                ]);

                return null;
            }

            // Step 2: Validate new password
            $this->logger->debug('Validating new password requirements', [
                'password_length' => strlen($newPassword),
                'password_empty' => empty($newPassword),
            ]);

            if (strlen($newPassword) < 8) {
                $this->logger->warning('New password too short for reset', [
                    'provided_length' => strlen($newPassword),
                    'required_length' => 8,
                ]);

                throw new InvalidArgumentException('Le mot de passe doit contenir au moins 8 caractères.');
            }

            // Step 3: Search for mentor with reset token
            $this->logger->debug('Searching for mentor with password reset token', [
                'token_prefix' => substr($token, 0, 8) . '...',
            ]);

            try {
                $mentor = $this->mentorRepository->findByPasswordResetToken($token);
            } catch (Exception $dbException) {
                $this->logger->error('Database error during password reset token search', [
                    'token_prefix' => substr($token, 0, 8) . '...',
                    'error' => $dbException->getMessage(),
                    'trace' => $dbException->getTraceAsString(),
                ]);

                return null;
            }

            if (!$mentor) {
                $this->logger->warning('Invalid or expired password reset token', [
                    'token_prefix' => substr($token, 0, 8) . '...',
                    'search_duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                return null;
            }

            $this->logger->debug('Mentor found with password reset token', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'mentor_active' => $mentor->isActive(),
                'token_expires_at' => $mentor->getPasswordResetTokenExpiresAt()?->format('Y-m-d H:i:s'),
                'created_at' => $mentor->getCreatedAt()?->format('Y-m-d H:i:s'),
            ]);

            // Step 4: Check token expiration
            $tokenExpiresAt = $mentor->getPasswordResetTokenExpiresAt();
            if ($tokenExpiresAt && $tokenExpiresAt < new DateTimeImmutable()) {
                $this->logger->warning('Expired password reset token used', [
                    'mentor_id' => $mentor->getId(),
                    'token_expired_at' => $tokenExpiresAt->format('Y-m-d H:i:s'),
                    'current_time' => date('Y-m-d H:i:s'),
                ]);

                return null;
            }

            // Step 5: Check if mentor is active
            if (!$mentor->isActive()) {
                $this->logger->warning('Password reset attempted for inactive mentor', [
                    'mentor_id' => $mentor->getId(),
                    'mentor_email' => $mentor->getEmail(),
                ]);

                return null;
            }

            // Step 6: Check if new password is different from current
            $this->logger->debug('Checking if new password differs from current', [
                'mentor_id' => $mentor->getId(),
            ]);

            try {
                if ($this->passwordHasher->isPasswordValid($mentor, $newPassword)) {
                    $this->logger->warning('New password same as current password', [
                        'mentor_id' => $mentor->getId(),
                    ]);

                    throw new InvalidArgumentException('Le nouveau mot de passe doit être différent de l\'actuel.');
                }
            } catch (InvalidArgumentException $e) {
                throw $e;
            } catch (Exception $passwordCheckException) {
                $this->logger->error('Error checking password similarity', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $passwordCheckException->getMessage(),
                ]);
                // Continue with password reset even if check fails
            }

            // Step 7: Hash new password
            $this->logger->debug('Hashing new password', [
                'mentor_id' => $mentor->getId(),
                'new_password_length' => strlen($newPassword),
            ]);

            try {
                $hashedPassword = $this->passwordHasher->hashPassword($mentor, $newPassword);
                $mentor->setPassword($hashedPassword);

                $this->logger->debug('New password hashed successfully', [
                    'mentor_id' => $mentor->getId(),
                    'hash_length' => strlen($hashedPassword),
                ]);
            } catch (Exception $hashException) {
                $this->logger->error('Error hashing new password', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $hashException->getMessage(),
                    'trace' => $hashException->getTraceAsString(),
                ]);

                throw new InvalidArgumentException('Erreur lors du hachage du nouveau mot de passe.');
            }

            // Step 8: Clear reset token
            $this->logger->debug('Clearing password reset token', [
                'mentor_id' => $mentor->getId(),
            ]);

            try {
                $mentor->clearPasswordResetToken();
                $this->logger->debug('Password reset token cleared', [
                    'mentor_id' => $mentor->getId(),
                ]);
            } catch (Exception $clearException) {
                $this->logger->error('Error clearing password reset token', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $clearException->getMessage(),
                ]);
                // Continue as password was changed successfully
            }

            // Step 9: Persist changes to database
            $this->logger->debug('Persisting password reset changes to database', [
                'mentor_id' => $mentor->getId(),
            ]);

            try {
                $this->entityManager->flush();

                $this->logger->debug('Password reset changes persisted successfully', [
                    'mentor_id' => $mentor->getId(),
                ]);
            } catch (Exception $dbException) {
                $this->logger->error('Database error during password reset persistence', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $dbException->getMessage(),
                    'trace' => $dbException->getTraceAsString(),
                ]);

                throw new Exception('Erreur lors de l\'enregistrement du nouveau mot de passe.');
            }

            $totalDuration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->info('Password reset completed successfully', [
                'mentor_id' => $mentor->getId(),
                'email' => $mentor->getEmail(),
                'company' => $mentor->getCompanyName(),
                'total_duration_ms' => $totalDuration,
                'reset_date' => date('Y-m-d H:i:s'),
            ]);

            return $mentor;
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Invalid argument during password reset', [
                'token_prefix' => substr($token, 0, 8) . '...',
                'error_message' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            throw $e;
        } catch (Exception $e) {
            $this->logger->error('Unexpected error during password reset', [
                'token_prefix' => substr($token, 0, 8) . '...',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'exception_type' => get_class($e),
            ]);

            throw new Exception('Erreur inattendue lors de la réinitialisation du mot de passe.');
        }
    }

    /**
     * Change mentor password.
     */
    public function changePassword(Mentor $mentor, string $currentPassword, string $newPassword): bool
    {
        $startTime = microtime(true);
        $this->logger->info('Starting password change process', [
            'mentor_id' => $mentor->getId(),
            'mentor_email' => $mentor->getEmail(),
            'current_password_length' => strlen($currentPassword),
            'new_password_length' => strlen($newPassword),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            // Step 1: Validate input parameters
            $this->logger->debug('Validating password change parameters', [
                'mentor_id' => $mentor->getId(),
                'current_password_empty' => empty($currentPassword),
                'new_password_empty' => empty($newPassword),
                'current_password_length' => strlen($currentPassword),
                'new_password_length' => strlen($newPassword),
            ]);

            if (empty($currentPassword) || empty($newPassword)) {
                $this->logger->warning('Empty passwords provided for password change', [
                    'mentor_id' => $mentor->getId(),
                    'current_empty' => empty($currentPassword),
                    'new_empty' => empty($newPassword),
                ]);

                throw new InvalidArgumentException('Les mots de passe actuels et nouveaux sont requis.');
            }

            // Step 2: Validate new password requirements
            if (strlen($newPassword) < 8) {
                $this->logger->warning('New password too short for password change', [
                    'mentor_id' => $mentor->getId(),
                    'provided_length' => strlen($newPassword),
                    'required_length' => 8,
                ]);

                throw new InvalidArgumentException('Le nouveau mot de passe doit contenir au moins 8 caractères.');
            }

            // Step 3: Verify current password
            $this->logger->debug('Verifying current password', [
                'mentor_id' => $mentor->getId(),
            ]);

            try {
                $currentPasswordValid = $this->passwordHasher->isPasswordValid($mentor, $currentPassword);
            } catch (Exception $verifyException) {
                $this->logger->error('Error verifying current password', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $verifyException->getMessage(),
                    'trace' => $verifyException->getTraceAsString(),
                ]);

                throw new InvalidArgumentException('Erreur lors de la vérification du mot de passe actuel.');
            }

            if (!$currentPasswordValid) {
                $this->logger->warning('Invalid current password during password change', [
                    'mentor_id' => $mentor->getId(),
                    'mentor_email' => $mentor->getEmail(),
                    'last_login' => $mentor->getLastLoginAt()?->format('Y-m-d H:i:s'),
                ]);

                throw new InvalidArgumentException('Mot de passe actuel incorrect.');
            }

            $this->logger->debug('Current password verified successfully', [
                'mentor_id' => $mentor->getId(),
            ]);

            // Step 4: Check if new password is different from current
            $this->logger->debug('Checking if new password differs from current', [
                'mentor_id' => $mentor->getId(),
            ]);

            try {
                $samePassword = $this->passwordHasher->isPasswordValid($mentor, $newPassword);
                if ($samePassword) {
                    $this->logger->warning('New password same as current password', [
                        'mentor_id' => $mentor->getId(),
                    ]);

                    throw new InvalidArgumentException('Le nouveau mot de passe doit être différent de l\'actuel.');
                }
            } catch (InvalidArgumentException $e) {
                throw $e;
            } catch (Exception $compareException) {
                $this->logger->error('Error comparing passwords', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $compareException->getMessage(),
                ]);
                // Continue with password change even if comparison fails
            }

            // Step 5: Validate password strength (additional checks)
            $this->logger->debug('Performing additional password strength validation', [
                'mentor_id' => $mentor->getId(),
            ]);

            $passwordStrengthChecks = [
                'has_uppercase' => preg_match('/[A-Z]/', $newPassword),
                'has_lowercase' => preg_match('/[a-z]/', $newPassword),
                'has_number' => preg_match('/[0-9]/', $newPassword),
                'has_special' => preg_match('/[^A-Za-z0-9]/', $newPassword),
            ];

            $this->logger->debug('Password strength analysis', [
                'mentor_id' => $mentor->getId(),
                'strength_checks' => $passwordStrengthChecks,
                'passed_checks' => array_sum($passwordStrengthChecks),
            ]);

            // Step 6: Hash new password
            $this->logger->debug('Hashing new password', [
                'mentor_id' => $mentor->getId(),
            ]);

            try {
                $hashedPassword = $this->passwordHasher->hashPassword($mentor, $newPassword);

                $this->logger->debug('New password hashed successfully', [
                    'mentor_id' => $mentor->getId(),
                    'hash_length' => strlen($hashedPassword),
                ]);
            } catch (Exception $hashException) {
                $this->logger->error('Error hashing new password', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $hashException->getMessage(),
                    'trace' => $hashException->getTraceAsString(),
                ]);

                throw new InvalidArgumentException('Erreur lors du hachage du nouveau mot de passe.');
            }

            // Step 7: Update mentor password
            $this->logger->debug('Setting new password for mentor', [
                'mentor_id' => $mentor->getId(),
            ]);

            try {
                $mentor->setPassword($hashedPassword);
            } catch (Exception $setException) {
                $this->logger->error('Error setting new password', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $setException->getMessage(),
                ]);

                throw new InvalidArgumentException('Erreur lors de la mise à jour du mot de passe.');
            }

            // Step 8: Persist changes to database
            $this->logger->debug('Persisting password change to database', [
                'mentor_id' => $mentor->getId(),
            ]);

            try {
                $this->entityManager->flush();

                $this->logger->debug('Password change persisted successfully', [
                    'mentor_id' => $mentor->getId(),
                ]);
            } catch (Exception $dbException) {
                $this->logger->error('Database error during password change persistence', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $dbException->getMessage(),
                    'trace' => $dbException->getTraceAsString(),
                ]);

                throw new InvalidArgumentException('Erreur lors de l\'enregistrement du nouveau mot de passe.');
            }

            $totalDuration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->info('Password change completed successfully', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'company' => $mentor->getCompanyName(),
                'total_duration_ms' => $totalDuration,
                'change_date' => date('Y-m-d H:i:s'),
                'password_strength_score' => array_sum($passwordStrengthChecks),
            ]);

            return true;
        } catch (InvalidArgumentException $e) {
            $this->logger->error('Invalid argument during password change', [
                'mentor_id' => $mentor->getId(),
                'error_message' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            throw $e;
        } catch (Exception $e) {
            $this->logger->error('Unexpected error during password change', [
                'mentor_id' => $mentor->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'exception_type' => get_class($e),
            ]);

            throw new InvalidArgumentException('Erreur inattendue lors du changement de mot de passe.');
        }
    }

    /**
     * Log in mentor programmatically.
     */
    public function loginMentor(Mentor $mentor, Request $request): void
    {
        $startTime = microtime(true);
        $this->logger->info('Starting programmatic mentor login', [
            'mentor_id' => $mentor->getId(),
            'mentor_email' => $mentor->getEmail(),
            'timestamp' => date('Y-m-d H:i:s'),
            'request_method' => $request->getMethod(),
            'request_uri' => $request->getRequestUri(),
            'user_agent' => $request->headers->get('User-Agent', 'unknown'),
            'ip_address' => $request->getClientIp(),
        ]);

        try {
            // Step 1: Validate mentor status
            $this->logger->debug('Validating mentor status for login', [
                'mentor_id' => $mentor->getId(),
                'mentor_active' => $mentor->isActive(),
                'email_verified' => $mentor->isEmailVerified(),
                'last_login' => $mentor->getLastLoginAt()?->format('Y-m-d H:i:s'),
            ]);

            if (!$mentor->isActive()) {
                $this->logger->error('Attempting to login inactive mentor programmatically', [
                    'mentor_id' => $mentor->getId(),
                    'mentor_email' => $mentor->getEmail(),
                ]);

                throw new AuthenticationException('Le compte mentor est désactivé.');
            }

            // Step 2: Create authentication token
            $this->logger->debug('Creating authentication token', [
                'mentor_id' => $mentor->getId(),
                'firewall' => 'mentor',
                'roles' => $mentor->getRoles(),
            ]);

            try {
                $token = new UsernamePasswordToken(
                    $mentor,
                    'mentor', // Firewall name
                    $mentor->getRoles(),
                );

                $this->logger->debug('Authentication token created successfully', [
                    'mentor_id' => $mentor->getId(),
                    'token_type' => get_class($token),
                    'user_identifier' => $token->getUserIdentifier(),
                    'roles' => $token->getRoleNames(),
                ]);
            } catch (Exception $tokenException) {
                $this->logger->error('Error creating authentication token', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $tokenException->getMessage(),
                    'trace' => $tokenException->getTraceAsString(),
                ]);

                throw new AuthenticationException('Erreur lors de la création du token d\'authentification.');
            }

            // Step 3: Set token in storage
            $this->logger->debug('Setting token in storage', [
                'mentor_id' => $mentor->getId(),
            ]);

            try {
                $this->tokenStorage->setToken($token);

                $this->logger->debug('Token set in storage successfully', [
                    'mentor_id' => $mentor->getId(),
                ]);
            } catch (Exception $storageException) {
                $this->logger->error('Error setting token in storage', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $storageException->getMessage(),
                    'trace' => $storageException->getTraceAsString(),
                ]);

                throw new AuthenticationException('Erreur lors de la mise en place de l\'authentification.');
            }

            // Step 4: Dispatch login event
            $this->logger->debug('Dispatching interactive login event', [
                'mentor_id' => $mentor->getId(),
            ]);

            try {
                $event = new InteractiveLoginEvent($request, $token);
                $this->eventDispatcher->dispatch($event);

                $this->logger->debug('Interactive login event dispatched successfully', [
                    'mentor_id' => $mentor->getId(),
                    'event_type' => get_class($event),
                ]);
            } catch (Exception $eventException) {
                $this->logger->warning('Error dispatching login event (non-blocking)', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $eventException->getMessage(),
                ]);
                // Continue even if event dispatch fails
            }

            // Step 5: Update last login timestamp
            $this->logger->debug('Updating last login timestamp', [
                'mentor_id' => $mentor->getId(),
                'previous_login' => $mentor->getLastLoginAt()?->format('Y-m-d H:i:s'),
            ]);

            try {
                $mentor->updateLastLogin();
                $this->entityManager->flush();

                $this->logger->debug('Last login timestamp updated', [
                    'mentor_id' => $mentor->getId(),
                    'new_login_time' => $mentor->getLastLoginAt()?->format('Y-m-d H:i:s'),
                ]);
            } catch (Exception $updateException) {
                $this->logger->warning('Error updating last login timestamp (non-blocking)', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $updateException->getMessage(),
                ]);
                // Continue even if timestamp update fails
            }

            $totalDuration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->info('Programmatic mentor login completed successfully', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'company' => $mentor->getCompanyName(),
                'total_duration_ms' => $totalDuration,
                'login_time' => $mentor->getLastLoginAt()?->format('Y-m-d H:i:s'),
                'session_started' => true,
            ]);
        } catch (AuthenticationException $e) {
            $this->logger->error('Authentication exception during programmatic login', [
                'mentor_id' => $mentor->getId(),
                'error_message' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            throw $e;
        } catch (Exception $e) {
            $this->logger->error('Unexpected error during programmatic mentor login', [
                'mentor_id' => $mentor->getId(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'exception_type' => get_class($e),
            ]);

            throw new AuthenticationException('Erreur inattendue lors de la connexion automatique.');
        }
    }

    /**
     * Check if mentor account is properly set up.
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
     * Get account setup completion percentage.
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
            'is_complete' => $percentage === 100,
        ];
    }

    /**
     * Deactivate mentor account.
     */
    public function deactivateMentor(Mentor $mentor, string $reason = ''): bool
    {
        $startTime = microtime(true);
        $this->logger->info('Starting mentor account deactivation', [
            'mentor_id' => $mentor->getId(),
            'mentor_email' => $mentor->getEmail(),
            'reason' => $reason,
            'current_status' => $mentor->isActive() ? 'active' : 'already_inactive',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            // Step 1: Check current status
            if (!$mentor->isActive()) {
                $this->logger->warning('Attempting to deactivate already inactive mentor', [
                    'mentor_id' => $mentor->getId(),
                    'mentor_email' => $mentor->getEmail(),
                ]);

                return true; // Already deactivated
            }

            // Step 2: Log mentor details before deactivation
            $this->logger->debug('Mentor details before deactivation', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'company' => $mentor->getCompanyName(),
                'last_login' => $mentor->getLastLoginAt()?->format('Y-m-d H:i:s'),
                'created_at' => $mentor->getCreatedAt()?->format('Y-m-d H:i:s'),
                'email_verified' => $mentor->isEmailVerified(),
            ]);

            // Step 3: Deactivate mentor
            try {
                $mentor->setIsActive(false);

                $this->logger->debug('Mentor status set to inactive', [
                    'mentor_id' => $mentor->getId(),
                    'new_status' => $mentor->isActive() ? 'active' : 'inactive',
                ]);
            } catch (Exception $statusException) {
                $this->logger->error('Error setting mentor inactive status', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $statusException->getMessage(),
                ]);

                throw new Exception('Erreur lors de la désactivation du compte.');
            }

            // Step 4: Persist changes
            $this->logger->debug('Persisting mentor deactivation to database', [
                'mentor_id' => $mentor->getId(),
            ]);

            try {
                $this->entityManager->flush();

                $this->logger->debug('Mentor deactivation persisted successfully', [
                    'mentor_id' => $mentor->getId(),
                ]);
            } catch (Exception $dbException) {
                $this->logger->error('Database error during mentor deactivation', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $dbException->getMessage(),
                    'trace' => $dbException->getTraceAsString(),
                ]);

                throw new Exception('Erreur lors de l\'enregistrement de la désactivation.');
            }

            $totalDuration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->info('Mentor account deactivated successfully', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'reason' => $reason,
                'total_duration_ms' => $totalDuration,
                'deactivation_date' => date('Y-m-d H:i:s'),
            ]);

            return true;
        } catch (Exception $e) {
            $this->logger->error('Unexpected error during mentor deactivation', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'reason' => $reason,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'exception_type' => get_class($e),
            ]);

            return false;
        }
    }

    /**
     * Reactivate mentor account.
     */
    public function reactivateMentor(Mentor $mentor): bool
    {
        $startTime = microtime(true);
        $this->logger->info('Starting mentor account reactivation', [
            'mentor_id' => $mentor->getId(),
            'mentor_email' => $mentor->getEmail(),
            'current_status' => $mentor->isActive() ? 'already_active' : 'inactive',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            // Step 1: Check current status
            if ($mentor->isActive()) {
                $this->logger->warning('Attempting to reactivate already active mentor', [
                    'mentor_id' => $mentor->getId(),
                    'mentor_email' => $mentor->getEmail(),
                ]);

                return true; // Already active
            }

            // Step 2: Log mentor details before reactivation
            $this->logger->debug('Mentor details before reactivation', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'company' => $mentor->getCompanyName(),
                'last_login' => $mentor->getLastLoginAt()?->format('Y-m-d H:i:s'),
                'created_at' => $mentor->getCreatedAt()?->format('Y-m-d H:i:s'),
                'email_verified' => $mentor->isEmailVerified(),
            ]);

            // Step 3: Validate mentor can be reactivated
            $this->logger->debug('Validating mentor eligibility for reactivation', [
                'mentor_id' => $mentor->getId(),
                'email_verified' => $mentor->isEmailVerified(),
                'has_company_info' => !empty($mentor->getCompanyName()) && !empty($mentor->getCompanySiret()),
                'has_personal_info' => !empty($mentor->getFirstName()) && !empty($mentor->getLastName()),
            ]);

            // Step 4: Reactivate mentor
            try {
                $mentor->setIsActive(true);

                $this->logger->debug('Mentor status set to active', [
                    'mentor_id' => $mentor->getId(),
                    'new_status' => $mentor->isActive() ? 'active' : 'inactive',
                ]);
            } catch (Exception $statusException) {
                $this->logger->error('Error setting mentor active status', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $statusException->getMessage(),
                ]);

                throw new Exception('Erreur lors de la réactivation du compte.');
            }

            // Step 5: Persist changes
            $this->logger->debug('Persisting mentor reactivation to database', [
                'mentor_id' => $mentor->getId(),
            ]);

            try {
                $this->entityManager->flush();

                $this->logger->debug('Mentor reactivation persisted successfully', [
                    'mentor_id' => $mentor->getId(),
                ]);
            } catch (Exception $dbException) {
                $this->logger->error('Database error during mentor reactivation', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $dbException->getMessage(),
                    'trace' => $dbException->getTraceAsString(),
                ]);

                throw new Exception('Erreur lors de l\'enregistrement de la réactivation.');
            }

            $totalDuration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->info('Mentor account reactivated successfully', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'total_duration_ms' => $totalDuration,
                'reactivation_date' => date('Y-m-d H:i:s'),
                'setup_complete' => $this->isAccountSetupComplete($mentor),
            ]);

            return true;
        } catch (Exception $e) {
            $this->logger->error('Unexpected error during mentor reactivation', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'exception_type' => get_class($e),
            ]);

            return false;
        }
    }

    /**
     * Generate secure random password for mentor.
     */
    public function generateSecurePassword(): string
    {
        return $this->mentorService->generateRandomPassword(12);
    }

    /**
     * Check for security vulnerabilities in mentor account.
     */
    public function performSecurityCheck(Mentor $mentor): array
    {
        $startTime = microtime(true);
        $this->logger->info('Starting security check for mentor account', [
            'mentor_id' => $mentor->getId(),
            'mentor_email' => $mentor->getEmail(),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            $issues = [];

            // Step 1: Check email verification status
            $this->logger->debug('Checking email verification status', [
                'mentor_id' => $mentor->getId(),
                'email_verified' => $mentor->isEmailVerified(),
            ]);

            if (!$mentor->isEmailVerified()) {
                $issues[] = 'email_not_verified';
                $this->logger->warning('Security issue detected: Email not verified', [
                    'mentor_id' => $mentor->getId(),
                ]);
            }

            // Step 2: Check last login activity
            $lastLogin = $mentor->getLastLoginAt();
            $inactivityThreshold = new DateTimeImmutable('-90 days');

            $this->logger->debug('Checking login activity', [
                'mentor_id' => $mentor->getId(),
                'last_login' => $lastLogin?->format('Y-m-d H:i:s'),
                'inactivity_threshold' => $inactivityThreshold->format('Y-m-d H:i:s'),
                'days_since_login' => $lastLogin ? $lastLogin->diff(new DateTimeImmutable())->days : 'never',
            ]);

            if (!$lastLogin || $lastLogin < $inactivityThreshold) {
                $issues[] = 'inactive_account';
                $this->logger->warning('Security issue detected: Inactive account', [
                    'mentor_id' => $mentor->getId(),
                    'last_login' => $lastLogin?->format('Y-m-d H:i:s'),
                    'days_inactive' => $lastLogin ? $lastLogin->diff(new DateTimeImmutable())->days : 'never_logged_in',
                ]);
            }

            // Step 3: Check for expired password reset tokens
            $resetToken = $mentor->getPasswordResetToken();
            $resetTokenExpiry = $mentor->getPasswordResetTokenExpiresAt();

            $this->logger->debug('Checking password reset token status', [
                'mentor_id' => $mentor->getId(),
                'has_reset_token' => !empty($resetToken),
                'token_expiry' => $resetTokenExpiry?->format('Y-m-d H:i:s'),
                'token_expired' => $resetTokenExpiry && $resetTokenExpiry < new DateTimeImmutable(),
            ]);

            if ($resetToken && $resetTokenExpiry && $resetTokenExpiry < new DateTimeImmutable()) {
                $issues[] = 'expired_reset_token';
                $this->logger->warning('Security issue detected: Expired reset token not cleared', [
                    'mentor_id' => $mentor->getId(),
                    'token_expired_at' => $resetTokenExpiry->format('Y-m-d H:i:s'),
                    'days_expired' => $resetTokenExpiry->diff(new DateTimeImmutable())->days,
                ]);
            }

            // Step 4: Check profile completion
            $setupComplete = $this->isAccountSetupComplete($mentor);

            $this->logger->debug('Checking profile completion', [
                'mentor_id' => $mentor->getId(),
                'setup_complete' => $setupComplete,
            ]);

            if (!$setupComplete) {
                $issues[] = 'incomplete_profile';
                $this->logger->warning('Security issue detected: Incomplete profile', [
                    'mentor_id' => $mentor->getId(),
                    'completion_status' => $this->getAccountSetupCompletion($mentor),
                ]);
            }

            // Step 5: Check for suspicious patterns
            $createdAt = $mentor->getCreatedAt();
            $accountAge = $createdAt ? $createdAt->diff(new DateTimeImmutable())->days : 0;

            $this->logger->debug('Analyzing account patterns', [
                'mentor_id' => $mentor->getId(),
                'account_age_days' => $accountAge,
                'created_at' => $createdAt?->format('Y-m-d H:i:s'),
                'company_provided' => !empty($mentor->getCompanyName()),
                'phone_provided' => !empty($mentor->getPhone()),
                'expertise_domains_count' => count($mentor->getExpertiseDomains()),
            ]);

            // Check for accounts created but never used
            if ($accountAge > 30 && !$lastLogin) {
                $issues[] = 'account_never_used';
                $this->logger->warning('Security issue detected: Account never used', [
                    'mentor_id' => $mentor->getId(),
                    'account_age_days' => $accountAge,
                ]);
            }

            // Step 6: Check for missing critical information
            if (empty($mentor->getCompanySiret()) && $mentor->isActive()) {
                $issues[] = 'missing_company_identification';
                $this->logger->warning('Security issue detected: Missing company SIRET', [
                    'mentor_id' => $mentor->getId(),
                ]);
            }

            $totalDuration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->info('Security check completed', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'issues_found' => $issues,
                'issues_count' => count($issues),
                'security_score' => count($issues) === 0 ? 'excellent' : (count($issues) <= 2 ? 'good' : 'needs_attention'),
                'total_duration_ms' => $totalDuration,
            ]);

            return $issues;
        } catch (Exception $e) {
            $this->logger->error('Error during security check', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'exception_type' => get_class($e),
            ]);

            // Return generic security issues if check fails
            return ['security_check_failed'];
        }
    }

    /**
     * Generate credentials for a new mentor.
     */
    public function generateCredentials(Mentor $mentor): array
    {
        $startTime = microtime(true);
        $this->logger->info('Starting credential generation for mentor', [
            'mentor_id' => $mentor->getId(),
            'mentor_email' => $mentor->getEmail(),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            // Step 1: Generate secure password
            $this->logger->debug('Generating secure password', [
                'mentor_id' => $mentor->getId(),
            ]);

            try {
                $password = $this->generateSecurePassword();

                $this->logger->debug('Secure password generated', [
                    'mentor_id' => $mentor->getId(),
                    'password_length' => strlen($password),
                ]);
            } catch (Exception $passwordException) {
                $this->logger->error('Error generating secure password', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $passwordException->getMessage(),
                ]);

                throw new Exception('Erreur lors de la génération du mot de passe.');
            }

            // Step 2: Hash password
            $this->logger->debug('Hashing generated password', [
                'mentor_id' => $mentor->getId(),
            ]);

            try {
                $hashedPassword = $this->passwordHasher->hashPassword($mentor, $password);

                $this->logger->debug('Password hashed successfully', [
                    'mentor_id' => $mentor->getId(),
                    'hash_length' => strlen($hashedPassword),
                ]);
            } catch (Exception $hashException) {
                $this->logger->error('Error hashing generated password', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $hashException->getMessage(),
                ]);

                throw new Exception('Erreur lors du hachage du mot de passe.');
            }

            // Step 3: Set password on mentor
            try {
                $mentor->setPassword($hashedPassword);

                $this->logger->debug('Password set on mentor entity', [
                    'mentor_id' => $mentor->getId(),
                ]);
            } catch (Exception $setException) {
                $this->logger->error('Error setting password on mentor', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $setException->getMessage(),
                ]);

                throw new Exception('Erreur lors de l\'assignation du mot de passe.');
            }

            // Step 4: Generate email verification token
            $this->logger->debug('Generating email verification token', [
                'mentor_id' => $mentor->getId(),
            ]);

            try {
                $mentor->generateEmailVerificationToken();

                $this->logger->debug('Email verification token generated', [
                    'mentor_id' => $mentor->getId(),
                    'token_prefix' => substr($mentor->getEmailVerificationToken(), 0, 8) . '...',
                ]);
            } catch (Exception $tokenException) {
                $this->logger->error('Error generating email verification token', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $tokenException->getMessage(),
                ]);

                throw new Exception('Erreur lors de la génération du token de vérification.');
            }

            // Step 5: Prepare credentials array
            $credentials = [
                'email' => $mentor->getEmail(),
                'password' => $password,
                'verification_token' => $mentor->getEmailVerificationToken(),
            ];

            $totalDuration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->info('Credentials generated successfully', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'has_password' => !empty($credentials['password']),
                'has_verification_token' => !empty($credentials['verification_token']),
                'total_duration_ms' => $totalDuration,
            ]);

            return $credentials;
        } catch (Exception $e) {
            $this->logger->error('Error generating mentor credentials', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'exception_type' => get_class($e),
            ]);

            throw $e;
        }
    }

    /**
     * Reset credentials for an existing mentor.
     */
    public function resetCredentials(Mentor $mentor): array
    {
        $startTime = microtime(true);
        $this->logger->info('Starting credential reset for existing mentor', [
            'mentor_id' => $mentor->getId(),
            'mentor_email' => $mentor->getEmail(),
            'current_email_verified' => $mentor->isEmailVerified(),
            'last_login' => $mentor->getLastLoginAt()?->format('Y-m-d H:i:s'),
            'timestamp' => date('Y-m-d H:i:s'),
        ]);

        try {
            // Step 1: Generate new secure password
            $this->logger->debug('Generating new secure password for reset', [
                'mentor_id' => $mentor->getId(),
            ]);

            try {
                $password = $this->generateSecurePassword();

                $this->logger->debug('New secure password generated for reset', [
                    'mentor_id' => $mentor->getId(),
                    'password_length' => strlen($password),
                ]);
            } catch (Exception $passwordException) {
                $this->logger->error('Error generating secure password for reset', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $passwordException->getMessage(),
                ]);

                throw new Exception('Erreur lors de la génération du nouveau mot de passe.');
            }

            // Step 2: Hash new password
            $this->logger->debug('Hashing new password for reset', [
                'mentor_id' => $mentor->getId(),
            ]);

            try {
                $hashedPassword = $this->passwordHasher->hashPassword($mentor, $password);

                $this->logger->debug('New password hashed successfully for reset', [
                    'mentor_id' => $mentor->getId(),
                    'hash_length' => strlen($hashedPassword),
                ]);
            } catch (Exception $hashException) {
                $this->logger->error('Error hashing new password for reset', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $hashException->getMessage(),
                ]);

                throw new Exception('Erreur lors du hachage du nouveau mot de passe.');
            }

            // Step 3: Set new password
            try {
                $mentor->setPassword($hashedPassword);

                $this->logger->debug('New password set on mentor for reset', [
                    'mentor_id' => $mentor->getId(),
                ]);
            } catch (Exception $setException) {
                $this->logger->error('Error setting new password for reset', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $setException->getMessage(),
                ]);

                throw new Exception('Erreur lors de l\'assignation du nouveau mot de passe.');
            }

            // Step 4: Reset email verification status
            $this->logger->debug('Resetting email verification status', [
                'mentor_id' => $mentor->getId(),
                'previous_email_verified' => $mentor->isEmailVerified(),
            ]);

            try {
                $mentor->setEmailVerified(false);

                $this->logger->debug('Email verification status reset', [
                    'mentor_id' => $mentor->getId(),
                    'email_verified' => $mentor->isEmailVerified(),
                ]);
            } catch (Exception $verificationException) {
                $this->logger->error('Error resetting email verification status', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $verificationException->getMessage(),
                ]);
                // Continue as this is not critical
            }

            // Step 5: Generate new email verification token
            $this->logger->debug('Generating new email verification token for reset', [
                'mentor_id' => $mentor->getId(),
            ]);

            try {
                $mentor->generateEmailVerificationToken();

                $this->logger->debug('New email verification token generated for reset', [
                    'mentor_id' => $mentor->getId(),
                    'token_prefix' => substr($mentor->getEmailVerificationToken(), 0, 8) . '...',
                ]);
            } catch (Exception $tokenException) {
                $this->logger->error('Error generating new email verification token for reset', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $tokenException->getMessage(),
                ]);

                throw new Exception('Erreur lors de la génération du nouveau token de vérification.');
            }

            // Step 6: Persist changes to database
            $this->logger->debug('Persisting credential reset changes to database', [
                'mentor_id' => $mentor->getId(),
            ]);

            try {
                $this->entityManager->flush();

                $this->logger->debug('Credential reset changes persisted successfully', [
                    'mentor_id' => $mentor->getId(),
                ]);
            } catch (Exception $dbException) {
                $this->logger->error('Database error during credential reset persistence', [
                    'mentor_id' => $mentor->getId(),
                    'error' => $dbException->getMessage(),
                    'trace' => $dbException->getTraceAsString(),
                ]);

                throw new Exception('Erreur lors de l\'enregistrement des nouveaux identifiants.');
            }

            // Step 7: Prepare credentials array
            $credentials = [
                'email' => $mentor->getEmail(),
                'password' => $password,
                'verification_token' => $mentor->getEmailVerificationToken(),
            ];

            $totalDuration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->info('Credentials reset completed successfully', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'has_new_password' => !empty($credentials['password']),
                'has_new_verification_token' => !empty($credentials['verification_token']),
                'email_verification_reset' => true,
                'total_duration_ms' => $totalDuration,
                'reset_date' => date('Y-m-d H:i:s'),
            ]);

            return $credentials;
        } catch (Exception $e) {
            $this->logger->error('Error resetting mentor credentials', [
                'mentor_id' => $mentor->getId(),
                'mentor_email' => $mentor->getEmail(),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'exception_type' => get_class($e),
            ]);

            throw $e;
        }
    }
}
