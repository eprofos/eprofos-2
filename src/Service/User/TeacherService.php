<?php

declare(strict_types=1);

namespace App\Service\User;

use App\Entity\User\Teacher;
use App\Repository\User\TeacherRepository;
use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Error\Error;

/**
 * Teacher Service.
 *
 * Handles teacher-related business logic including email notifications,
 * password management, and account operations.
 */
class TeacherService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TeacherRepository $teacherRepository,
        private MailerInterface $mailer,
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator,
        private UserPasswordHasherInterface $passwordHasher,
        private LoggerInterface $logger,
    ) {}

    /**
     * Send password reset email to teacher.
     */
    public function sendPasswordResetEmail(Teacher $teacher): bool
    {
        $this->logger->info('Starting password reset email process for teacher', [
            'teacher_id' => $teacher->getId(),
            'teacher_email' => $teacher->getEmail(),
            'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
            'action' => 'send_password_reset_email',
            'timestamp' => new DateTime(),
        ]);

        try {
            // Generate password reset token
            $this->logger->debug('Generating password reset token for teacher', [
                'teacher_id' => $teacher->getId(),
                'existing_token' => $teacher->getPasswordResetToken() ? 'exists' : 'none',
            ]);

            $teacher->generatePasswordResetToken();

            $this->logger->debug('Persisting password reset token to database', [
                'teacher_id' => $teacher->getId(),
                'token_generated' => $teacher->getPasswordResetToken() ? 'success' : 'failed',
            ]);

            $this->entityManager->flush();

            // Generate reset URL
            $resetUrl = $this->urlGenerator->generate(
                'teacher_reset_password',
                ['token' => $teacher->getPasswordResetToken()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $this->logger->debug('Generated password reset URL', [
                'teacher_id' => $teacher->getId(),
                'reset_url_length' => strlen($resetUrl),
                'token_length' => strlen($teacher->getPasswordResetToken()),
            ]);

            // Render email template
            $this->logger->debug('Rendering password reset email template', [
                'teacher_id' => $teacher->getId(),
                'template' => 'emails/teacher/password_reset.html.twig',
            ]);

            $emailContent = $this->twig->render('emails/teacher/password_reset.html.twig', [
                'teacher' => $teacher,
                'reset_url' => $resetUrl,
            ]);

            // Create email
            $email = (new Email())
                ->from('noreply@eprofos.com')
                ->to($teacher->getEmail())
                ->subject('Réinitialisation de votre mot de passe - EPROFOS')
                ->html($emailContent)
            ;

            $this->logger->debug('Email object created, attempting to send', [
                'teacher_id' => $teacher->getId(),
                'from' => 'noreply@eprofos.com',
                'to' => $teacher->getEmail(),
                'subject' => 'Réinitialisation de votre mot de passe - EPROFOS',
                'content_length' => strlen($emailContent),
            ]);

            $this->mailer->send($email);

            $this->logger->info('Password reset email sent successfully to teacher', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getEmail(),
                'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
                'reset_token' => substr($teacher->getPasswordResetToken(), 0, 8) . '...',
                'reset_url_domain' => parse_url($resetUrl, PHP_URL_HOST),
                'email_sent_at' => new DateTime(),
                'action_result' => 'success',
            ]);

            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send password reset email to teacher - Transport Exception', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getEmail(),
                'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
                'error_type' => 'TransportExceptionInterface',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'action_result' => 'transport_error',
                'timestamp' => new DateTime(),
            ]);

            return false;
        } catch (Error $e) {
            $this->logger->error('Failed to send password reset email to teacher - Twig Template Error', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getEmail(),
                'error_type' => 'Twig_Error',
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'action_result' => 'template_error',
                'timestamp' => new DateTime(),
            ]);

            return false;
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Failed to send password reset email to teacher - Database Error', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getEmail(),
                'error_type' => 'Doctrine_DBAL_Exception',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'action_result' => 'database_error',
                'timestamp' => new DateTime(),
            ]);

            return false;
        } catch (Exception $e) {
            $this->logger->critical('Failed to send password reset email to teacher - Unexpected Error', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getEmail(),
                'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'action_result' => 'unexpected_error',
                'timestamp' => new DateTime(),
            ]);

            return false;
        }
    }

    /**
     * Send email verification to teacher.
     */
    public function sendEmailVerification(Teacher $teacher): bool
    {
        $this->logger->info('Starting email verification process for teacher', [
            'teacher_id' => $teacher->getId(),
            'teacher_email' => $teacher->getEmail(),
            'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
            'is_email_verified' => $teacher->isEmailVerified(),
            'existing_token' => $teacher->getEmailVerificationToken() ? 'exists' : 'none',
            'action' => 'send_email_verification',
            'timestamp' => new DateTime(),
        ]);

        try {
            // Generate email verification token if not exists
            if (!$teacher->getEmailVerificationToken()) {
                $this->logger->debug('Generating new email verification token for teacher', [
                    'teacher_id' => $teacher->getId(),
                    'reason' => 'no_existing_token',
                ]);

                $teacher->generateEmailVerificationToken();

                $this->logger->debug('Persisting email verification token to database', [
                    'teacher_id' => $teacher->getId(),
                    'token_generated' => $teacher->getEmailVerificationToken() ? 'success' : 'failed',
                ]);

                $this->entityManager->flush();
            } else {
                $this->logger->debug('Using existing email verification token for teacher', [
                    'teacher_id' => $teacher->getId(),
                    'token_length' => strlen($teacher->getEmailVerificationToken()),
                ]);
            }

            // Generate verification URL
            $verificationUrl = $this->urlGenerator->generate(
                'teacher_verify_email',
                ['token' => $teacher->getEmailVerificationToken()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $this->logger->debug('Generated email verification URL', [
                'teacher_id' => $teacher->getId(),
                'verification_url_length' => strlen($verificationUrl),
                'token_length' => strlen($teacher->getEmailVerificationToken()),
                'url_domain' => parse_url($verificationUrl, PHP_URL_HOST),
            ]);

            // Render email template
            $this->logger->debug('Rendering email verification template', [
                'teacher_id' => $teacher->getId(),
                'template' => 'emails/teacher/email_verification.html.twig',
            ]);

            $emailContent = $this->twig->render('emails/teacher/email_verification.html.twig', [
                'teacher' => $teacher,
                'verification_url' => $verificationUrl,
            ]);

            // Create email
            $email = (new Email())
                ->from('noreply@eprofos.com')
                ->to($teacher->getEmail())
                ->subject('Vérifiez votre adresse email - EPROFOS')
                ->html($emailContent)
            ;

            $this->logger->debug('Email verification object created, attempting to send', [
                'teacher_id' => $teacher->getId(),
                'from' => 'noreply@eprofos.com',
                'to' => $teacher->getEmail(),
                'subject' => 'Vérifiez votre adresse email - EPROFOS',
                'content_length' => strlen($emailContent),
            ]);

            $this->mailer->send($email);

            $this->logger->info('Email verification sent successfully to teacher', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getEmail(),
                'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
                'verification_token' => substr($teacher->getEmailVerificationToken(), 0, 8) . '...',
                'verification_url_domain' => parse_url($verificationUrl, PHP_URL_HOST),
                'email_sent_at' => new DateTime(),
                'action_result' => 'success',
            ]);

            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send email verification to teacher - Transport Exception', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getEmail(),
                'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
                'error_type' => 'TransportExceptionInterface',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'action_result' => 'transport_error',
                'timestamp' => new DateTime(),
            ]);

            return false;
        } catch (Error $e) {
            $this->logger->error('Failed to send email verification to teacher - Twig Template Error', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getEmail(),
                'error_type' => 'Twig_Error',
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'action_result' => 'template_error',
                'timestamp' => new DateTime(),
            ]);

            return false;
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Failed to send email verification to teacher - Database Error', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getEmail(),
                'error_type' => 'Doctrine_DBAL_Exception',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'action_result' => 'database_error',
                'timestamp' => new DateTime(),
            ]);

            return false;
        } catch (Exception $e) {
            $this->logger->critical('Failed to send email verification to teacher - Unexpected Error', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getEmail(),
                'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'action_result' => 'unexpected_error',
                'timestamp' => new DateTime(),
            ]);

            return false;
        }
    }

    /**
     * Send welcome email to new teacher.
     */
    public function sendWelcomeEmail(Teacher $teacher, ?string $tempPassword = null): bool
    {
        $this->logger->info('Starting welcome email process for new teacher', [
            'teacher_id' => $teacher->getId(),
            'teacher_email' => $teacher->getEmail(),
            'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
            'has_temp_password' => $tempPassword !== null,
            'teacher_created_at' => $teacher->getCreatedAt()?->format('Y-m-d H:i:s'),
            'action' => 'send_welcome_email',
            'timestamp' => new DateTime(),
        ]);

        try {
            // Generate login URL
            $loginUrl = $this->urlGenerator->generate(
                'teacher_login',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

            $this->logger->debug('Generated teacher login URL', [
                'teacher_id' => $teacher->getId(),
                'login_url_length' => strlen($loginUrl),
                'url_domain' => parse_url($loginUrl, PHP_URL_HOST),
            ]);

            // Render email template
            $this->logger->debug('Rendering welcome email template', [
                'teacher_id' => $teacher->getId(),
                'template' => 'emails/teacher/welcome.html.twig',
                'includes_temp_password' => $tempPassword !== null,
                'temp_password_length' => $tempPassword ? strlen($tempPassword) : 0,
            ]);

            $emailContent = $this->twig->render('emails/teacher/welcome.html.twig', [
                'teacher' => $teacher,
                'temp_password' => $tempPassword,
                'login_url' => $loginUrl,
            ]);

            // Create email
            $email = (new Email())
                ->from('noreply@eprofos.com')
                ->to($teacher->getEmail())
                ->subject('Bienvenue chez EPROFOS - Accès formateur')
                ->html($emailContent)
            ;

            $this->logger->debug('Welcome email object created, attempting to send', [
                'teacher_id' => $teacher->getId(),
                'from' => 'noreply@eprofos.com',
                'to' => $teacher->getEmail(),
                'subject' => 'Bienvenue chez EPROFOS - Accès formateur',
                'content_length' => strlen($emailContent),
                'includes_credentials' => $tempPassword !== null,
            ]);

            $this->mailer->send($email);

            $this->logger->info('Welcome email sent successfully to teacher', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getEmail(),
                'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
                'login_url_domain' => parse_url($loginUrl, PHP_URL_HOST),
                'temp_password_provided' => $tempPassword !== null,
                'email_sent_at' => new DateTime(),
                'action_result' => 'success',
            ]);

            return true;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Failed to send welcome email to teacher - Transport Exception', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getEmail(),
                'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
                'error_type' => 'TransportExceptionInterface',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'action_result' => 'transport_error',
                'timestamp' => new DateTime(),
            ]);

            return false;
        } catch (Error $e) {
            $this->logger->error('Failed to send welcome email to teacher - Twig Template Error', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getEmail(),
                'error_type' => 'Twig_Error',
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'action_result' => 'template_error',
                'timestamp' => new DateTime(),
            ]);

            return false;
        } catch (Exception $e) {
            $this->logger->critical('Failed to send welcome email to teacher - Unexpected Error', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getEmail(),
                'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'action_result' => 'unexpected_error',
                'timestamp' => new DateTime(),
            ]);

            return false;
        }
    }

    /**
     * Create teacher account.
     */
    public function createTeacher(array $data): Teacher
    {
        $this->logger->info('Starting teacher account creation process', [
            'email' => $data['email'] ?? 'not_provided',
            'firstName' => $data['firstName'] ?? 'not_provided',
            'lastName' => $data['lastName'] ?? 'not_provided',
            'has_password' => isset($data['password']) && !empty($data['password']),
            'data_keys' => array_keys($data),
            'action' => 'create_teacher',
            'timestamp' => new DateTime(),
        ]);

        try {
            // Validate required fields
            if (empty($data['firstName'])) {
                throw new InvalidArgumentException('First name is required');
            }
            if (empty($data['lastName'])) {
                throw new InvalidArgumentException('Last name is required');
            }
            if (empty($data['email'])) {
                throw new InvalidArgumentException('Email is required');
            }

            $this->logger->debug('Validating teacher email uniqueness', [
                'email' => $data['email'],
                'action' => 'email_validation',
            ]);

            // Check if email already exists
            if ($this->emailExists($data['email'])) {
                throw new InvalidArgumentException('Email already exists');
            }

            $this->logger->debug('Creating new teacher entity', [
                'email' => $data['email'],
                'firstName' => $data['firstName'],
                'lastName' => $data['lastName'],
            ]);

            $teacher = new Teacher();

            $teacher->setFirstName($data['firstName']);
            $teacher->setLastName($data['lastName']);
            $teacher->setEmail($data['email']);

            $this->logger->debug('Setting optional teacher fields', [
                'teacher_email' => $teacher->getEmail(),
                'has_phone' => isset($data['phone']),
                'has_specialty' => isset($data['specialty']),
                'has_title' => isset($data['title']),
                'has_years_experience' => isset($data['yearsOfExperience']),
                'has_biography' => isset($data['biography']),
                'has_qualifications' => isset($data['qualifications']),
            ]);

            if (isset($data['phone'])) {
                $teacher->setPhone($data['phone']);
            }

            if (isset($data['specialty'])) {
                $teacher->setSpecialty($data['specialty']);
            }

            if (isset($data['title'])) {
                $teacher->setTitle($data['title']);
            }

            if (isset($data['yearsOfExperience'])) {
                $teacher->setYearsOfExperience((int) $data['yearsOfExperience']);
            }

            if (isset($data['biography'])) {
                $teacher->setBiography($data['biography']);
            }

            if (isset($data['qualifications'])) {
                $teacher->setQualifications($data['qualifications']);
            }

            // Generate temporary password if not provided
            $password = $data['password'] ?? bin2hex(random_bytes(8));
            $tempPasswordGenerated = !isset($data['password']) || empty($data['password']);

            $this->logger->debug('Setting teacher password', [
                'teacher_email' => $teacher->getEmail(),
                'password_provided' => !$tempPasswordGenerated,
                'temp_password_generated' => $tempPasswordGenerated,
                'password_length' => strlen($password),
            ]);

            $hashedPassword = $this->passwordHasher->hashPassword($teacher, $password);
            $teacher->setPassword($hashedPassword);

            $this->logger->debug('Persisting teacher to database', [
                'teacher_email' => $teacher->getEmail(),
                'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
            ]);

            $this->entityManager->persist($teacher);
            $this->entityManager->flush();

            $this->logger->info('Teacher account created successfully', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getEmail(),
                'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
                'specialty' => $teacher->getSpecialty(),
                'title' => $teacher->getTitle(),
                'years_of_experience' => $teacher->getYearsOfExperience(),
                'has_phone' => $teacher->getPhone() !== null,
                'has_biography' => $teacher->getBiography() !== null,
                'has_qualifications' => !empty($teacher->getQualifications()),
                'temp_password_generated' => $tempPasswordGenerated,
                'created_at' => $teacher->getCreatedAt()?->format('Y-m-d H:i:s'),
                'action_result' => 'success',
                'timestamp' => new DateTime(),
            ]);

            return $teacher;
        } catch (InvalidArgumentException $e) {
            $this->logger->warning('Teacher creation failed - Invalid argument', [
                'email' => $data['email'] ?? 'not_provided',
                'error_type' => 'InvalidArgumentException',
                'error_message' => $e->getMessage(),
                'data_provided' => array_keys($data),
                'action_result' => 'validation_error',
                'timestamp' => new DateTime(),
            ]);

            throw $e;
        } catch (UniqueConstraintViolationException $e) {
            $this->logger->error('Teacher creation failed - Unique constraint violation', [
                'email' => $data['email'] ?? 'not_provided',
                'error_type' => 'UniqueConstraintViolationException',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'action_result' => 'constraint_violation',
                'timestamp' => new DateTime(),
            ]);

            throw new InvalidArgumentException('A teacher with this email already exists');
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Teacher creation failed - Database error', [
                'email' => $data['email'] ?? 'not_provided',
                'error_type' => 'Doctrine_DBAL_Exception',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'action_result' => 'database_error',
                'timestamp' => new DateTime(),
            ]);

            throw new RuntimeException('Failed to create teacher due to database error');
        } catch (Exception $e) {
            $this->logger->critical('Teacher creation failed - Unexpected error', [
                'email' => $data['email'] ?? 'not_provided',
                'firstName' => $data['firstName'] ?? 'not_provided',
                'lastName' => $data['lastName'] ?? 'not_provided',
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'action_result' => 'unexpected_error',
                'timestamp' => new DateTime(),
            ]);

            throw new RuntimeException('Failed to create teacher due to unexpected error');
        }
    }

    /**
     * Update teacher profile.
     */
    public function updateTeacher(Teacher $teacher, array $data): Teacher
    {
        $this->logger->info('Starting teacher profile update process', [
            'teacher_id' => $teacher->getId(),
            'teacher_email' => $teacher->getEmail(),
            'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
            'data_keys' => array_keys($data),
            'has_password_update' => isset($data['password']) && !empty($data['password']),
            'action' => 'update_teacher',
            'timestamp' => new DateTime(),
        ]);

        try {
            $originalData = [
                'firstName' => $teacher->getFirstName(),
                'lastName' => $teacher->getLastName(),
                'phone' => $teacher->getPhone(),
                'specialty' => $teacher->getSpecialty(),
                'title' => $teacher->getTitle(),
                'yearsOfExperience' => $teacher->getYearsOfExperience(),
                'biography' => $teacher->getBiography(),
                'qualifications' => $teacher->getQualifications(),
            ];

            $this->logger->debug('Capturing original teacher data before update', [
                'teacher_id' => $teacher->getId(),
                'original_data' => $originalData,
            ]);

            $changes = [];

            if (isset($data['firstName']) && $data['firstName'] !== $teacher->getFirstName()) {
                $changes['firstName'] = ['old' => $teacher->getFirstName(), 'new' => $data['firstName']];
                $teacher->setFirstName($data['firstName']);
            }

            if (isset($data['lastName']) && $data['lastName'] !== $teacher->getLastName()) {
                $changes['lastName'] = ['old' => $teacher->getLastName(), 'new' => $data['lastName']];
                $teacher->setLastName($data['lastName']);
            }

            if (isset($data['phone']) && $data['phone'] !== $teacher->getPhone()) {
                $changes['phone'] = ['old' => $teacher->getPhone(), 'new' => $data['phone']];
                $teacher->setPhone($data['phone']);
            }

            if (isset($data['specialty']) && $data['specialty'] !== $teacher->getSpecialty()) {
                $changes['specialty'] = ['old' => $teacher->getSpecialty(), 'new' => $data['specialty']];
                $teacher->setSpecialty($data['specialty']);
            }

            if (isset($data['title']) && $data['title'] !== $teacher->getTitle()) {
                $changes['title'] = ['old' => $teacher->getTitle(), 'new' => $data['title']];
                $teacher->setTitle($data['title']);
            }

            if (isset($data['yearsOfExperience']) && (int) $data['yearsOfExperience'] !== $teacher->getYearsOfExperience()) {
                $changes['yearsOfExperience'] = ['old' => $teacher->getYearsOfExperience(), 'new' => (int) $data['yearsOfExperience']];
                $teacher->setYearsOfExperience((int) $data['yearsOfExperience']);
            }

            if (isset($data['biography']) && $data['biography'] !== $teacher->getBiography()) {
                $changes['biography'] = ['old' => strlen($teacher->getBiography() ?? ''), 'new' => strlen($data['biography'])];
                $teacher->setBiography($data['biography']);
            }

            if (isset($data['qualifications']) && $data['qualifications'] !== $teacher->getQualifications()) {
                $oldQualifications = $teacher->getQualifications() ?? '';
                $newQualifications = $data['qualifications'] ?? '';
                $changes['qualifications'] = ['old' => strlen($oldQualifications), 'new' => strlen($newQualifications)];
                $teacher->setQualifications($data['qualifications']);
            }

            if (isset($data['password']) && !empty($data['password'])) {
                $this->logger->debug('Updating teacher password', [
                    'teacher_id' => $teacher->getId(),
                    'password_length' => strlen($data['password']),
                ]);

                $hashedPassword = $this->passwordHasher->hashPassword($teacher, $data['password']);
                $teacher->setPassword($hashedPassword);
                $changes['password'] = 'updated';
            }

            $this->logger->debug('Detected changes for teacher update', [
                'teacher_id' => $teacher->getId(),
                'changes' => $changes,
                'total_changes' => count($changes),
            ]);

            if (empty($changes)) {
                $this->logger->info('No changes detected for teacher update', [
                    'teacher_id' => $teacher->getId(),
                    'teacher_email' => $teacher->getEmail(),
                    'action_result' => 'no_changes',
                ]);

                return $teacher;
            }

            $this->logger->debug('Persisting teacher updates to database', [
                'teacher_id' => $teacher->getId(),
                'changes_count' => count($changes),
            ]);

            $this->entityManager->flush();

            $this->logger->info('Teacher profile updated successfully', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getEmail(),
                'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
                'changes_made' => array_keys($changes),
                'changes_count' => count($changes),
                'specialty' => $teacher->getSpecialty(),
                'title' => $teacher->getTitle(),
                'years_of_experience' => $teacher->getYearsOfExperience(),
                'has_phone' => $teacher->getPhone() !== null,
                'has_biography' => $teacher->getBiography() !== null,
                'qualifications_length' => strlen($teacher->getQualifications() ?? ''),
                'password_updated' => isset($changes['password']),
                'updated_at' => $teacher->getUpdatedAt()?->format('Y-m-d H:i:s'),
                'action_result' => 'success',
                'timestamp' => new DateTime(),
            ]);

            return $teacher;
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Teacher update failed - Database error', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getEmail(),
                'error_type' => 'Doctrine_DBAL_Exception',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'changes_attempted' => array_keys($data),
                'action_result' => 'database_error',
                'timestamp' => new DateTime(),
            ]);

            throw new RuntimeException('Failed to update teacher due to database error');
        } catch (Exception $e) {
            $this->logger->critical('Teacher update failed - Unexpected error', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getEmail(),
                'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'changes_attempted' => array_keys($data),
                'action_result' => 'unexpected_error',
                'timestamp' => new DateTime(),
            ]);

            throw new RuntimeException('Failed to update teacher due to unexpected error');
        }
    }

    /**
     * Deactivate teacher account.
     */
    public function deactivateTeacher(Teacher $teacher): void
    {
        $this->logger->info('Starting teacher deactivation process', [
            'teacher_id' => $teacher->getId(),
            'teacher_email' => $teacher->getEmail(),
            'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
            'current_status' => $teacher->isActive() ? 'active' : 'inactive',
            'action' => 'deactivate_teacher',
            'timestamp' => new DateTime(),
        ]);

        try {
            if (!$teacher->isActive()) {
                $this->logger->info('Teacher is already inactive', [
                    'teacher_id' => $teacher->getId(),
                    'teacher_email' => $teacher->getEmail(),
                    'action_result' => 'already_inactive',
                ]);

                return;
            }

            $this->logger->debug('Setting teacher status to inactive', [
                'teacher_id' => $teacher->getId(),
                'previous_status' => 'active',
            ]);

            $teacher->setIsActive(false);

            $this->logger->debug('Persisting teacher deactivation to database', [
                'teacher_id' => $teacher->getId(),
            ]);

            $this->entityManager->flush();

            $this->logger->info('Teacher account deactivated successfully', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getEmail(),
                'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
                'previous_status' => 'active',
                'new_status' => 'inactive',
                'deactivated_at' => new DateTime(),
                'action_result' => 'success',
                'timestamp' => new DateTime(),
            ]);
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Teacher deactivation failed - Database error', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getEmail(),
                'error_type' => 'Doctrine_DBAL_Exception',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'action_result' => 'database_error',
                'timestamp' => new DateTime(),
            ]);

            throw new RuntimeException('Failed to deactivate teacher due to database error');
        } catch (Exception $e) {
            $this->logger->critical('Teacher deactivation failed - Unexpected error', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getEmail(),
                'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'action_result' => 'unexpected_error',
                'timestamp' => new DateTime(),
            ]);

            throw new RuntimeException('Failed to deactivate teacher due to unexpected error');
        }
    }

    /**
     * Activate teacher account.
     */
    public function activateTeacher(Teacher $teacher): void
    {
        $this->logger->info('Starting teacher activation process', [
            'teacher_id' => $teacher->getId(),
            'teacher_email' => $teacher->getEmail(),
            'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
            'current_status' => $teacher->isActive() ? 'active' : 'inactive',
            'action' => 'activate_teacher',
            'timestamp' => new DateTime(),
        ]);

        try {
            if ($teacher->isActive()) {
                $this->logger->info('Teacher is already active', [
                    'teacher_id' => $teacher->getId(),
                    'teacher_email' => $teacher->getEmail(),
                    'action_result' => 'already_active',
                ]);

                return;
            }

            $this->logger->debug('Setting teacher status to active', [
                'teacher_id' => $teacher->getId(),
                'previous_status' => 'inactive',
            ]);

            $teacher->setIsActive(true);

            $this->logger->debug('Persisting teacher activation to database', [
                'teacher_id' => $teacher->getId(),
            ]);

            $this->entityManager->flush();

            $this->logger->info('Teacher account activated successfully', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getEmail(),
                'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
                'previous_status' => 'inactive',
                'new_status' => 'active',
                'activated_at' => new DateTime(),
                'action_result' => 'success',
                'timestamp' => new DateTime(),
            ]);
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Teacher activation failed - Database error', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getEmail(),
                'error_type' => 'Doctrine_DBAL_Exception',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'action_result' => 'database_error',
                'timestamp' => new DateTime(),
            ]);

            throw new RuntimeException('Failed to activate teacher due to database error');
        } catch (Exception $e) {
            $this->logger->critical('Teacher activation failed - Unexpected error', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getEmail(),
                'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'action_result' => 'unexpected_error',
                'timestamp' => new DateTime(),
            ]);

            throw new RuntimeException('Failed to activate teacher due to unexpected error');
        }
    }

    /**
     * Generate temporary password for teacher.
     */
    public function generateTemporaryPassword(Teacher $teacher): string
    {
        $this->logger->info('Starting temporary password generation for teacher', [
            'teacher_id' => $teacher->getId(),
            'teacher_email' => $teacher->getEmail(),
            'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
            'action' => 'generate_temporary_password',
            'timestamp' => new DateTime(),
        ]);

        try {
            $this->logger->debug('Generating random temporary password', [
                'teacher_id' => $teacher->getId(),
                'bytes_length' => 8,
            ]);

            $tempPassword = bin2hex(random_bytes(8));

            $this->logger->debug('Hashing temporary password', [
                'teacher_id' => $teacher->getId(),
                'temp_password_length' => strlen($tempPassword),
            ]);

            $hashedPassword = $this->passwordHasher->hashPassword($teacher, $tempPassword);

            $this->logger->debug('Setting hashed password for teacher', [
                'teacher_id' => $teacher->getId(),
                'hashed_password_length' => strlen($hashedPassword),
            ]);

            $teacher->setPassword($hashedPassword);

            $this->logger->debug('Persisting temporary password to database', [
                'teacher_id' => $teacher->getId(),
            ]);

            $this->entityManager->flush();

            $this->logger->info('Temporary password generated successfully for teacher', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getEmail(),
                'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
                'temp_password_length' => strlen($tempPassword),
                'generated_at' => new DateTime(),
                'action_result' => 'success',
                'timestamp' => new DateTime(),
            ]);

            return $tempPassword;
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Temporary password generation failed - Database error', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getEmail(),
                'error_type' => 'Doctrine_DBAL_Exception',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'action_result' => 'database_error',
                'timestamp' => new DateTime(),
            ]);

            throw new RuntimeException('Failed to generate temporary password due to database error');
        } catch (Exception $e) {
            $this->logger->critical('Temporary password generation failed - Unexpected error', [
                'teacher_id' => $teacher->getId(),
                'teacher_email' => $teacher->getEmail(),
                'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'action_result' => 'unexpected_error',
                'timestamp' => new DateTime(),
            ]);

            throw new RuntimeException('Failed to generate temporary password due to unexpected error');
        }
    }

    /**
     * Get teacher statistics.
     */
    public function getStatistics(): array
    {
        $this->logger->info('Starting teacher statistics retrieval', [
            'action' => 'get_teacher_statistics',
            'timestamp' => new DateTime(),
        ]);

        try {
            $this->logger->debug('Querying teacher statistics from repository', [
                'repository_class' => get_class($this->teacherRepository),
            ]);

            $total = $this->teacherRepository->countTotal();
            $active = $this->teacherRepository->countActive();
            $verified = $this->teacherRepository->countVerified();

            $statistics = [
                'total' => $total,
                'active' => $active,
                'verified' => $verified,
                'inactive' => $total - $active,
                'unverified' => $total - $verified,
                'activity_rate' => $total > 0 ? round(($active / $total) * 100, 2) : 0,
                'verification_rate' => $total > 0 ? round(($verified / $total) * 100, 2) : 0,
            ];

            $this->logger->info('Teacher statistics retrieved successfully', [
                'statistics' => $statistics,
                'query_performance' => [
                    'total_query_executed' => true,
                    'active_query_executed' => true,
                    'verified_query_executed' => true,
                ],
                'action_result' => 'success',
                'timestamp' => new DateTime(),
            ]);

            return $statistics;
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Teacher statistics retrieval failed - Database error', [
                'error_type' => 'Doctrine_DBAL_Exception',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'action_result' => 'database_error',
                'timestamp' => new DateTime(),
            ]);

            throw new RuntimeException('Failed to retrieve teacher statistics due to database error');
        } catch (Exception $e) {
            $this->logger->critical('Teacher statistics retrieval failed - Unexpected error', [
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'action_result' => 'unexpected_error',
                'timestamp' => new DateTime(),
            ]);

            throw new RuntimeException('Failed to retrieve teacher statistics due to unexpected error');
        }
    }

    /**
     * Find teachers by criteria.
     */
    public function findTeachersByCriteria(array $criteria): array
    {
        $this->logger->info('Starting teacher search by criteria', [
            'criteria' => $criteria,
            'criteria_count' => count($criteria),
            'action' => 'find_teachers_by_criteria',
            'timestamp' => new DateTime(),
        ]);

        try {
            $this->logger->debug('Validating search criteria', [
                'criteria_keys' => array_keys($criteria),
                'has_filters' => !empty($criteria),
            ]);

            // Validate criteria if needed
            $validCriteria = [];
            foreach ($criteria as $key => $value) {
                if (!empty($value)) {
                    $validCriteria[$key] = $value;
                }
            }

            $this->logger->debug('Executing teacher search query', [
                'valid_criteria' => $validCriteria,
                'valid_criteria_count' => count($validCriteria),
                'repository_method' => 'findWithFilters',
            ]);

            $teachers = $this->teacherRepository->findWithFilters($validCriteria);

            $this->logger->info('Teacher search completed successfully', [
                'original_criteria' => $criteria,
                'valid_criteria' => $validCriteria,
                'results_count' => count($teachers),
                'teachers_found' => array_map(static fn ($teacher) => [
                    'id' => $teacher->getId(),
                    'email' => $teacher->getEmail(),
                    'name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
                    'specialty' => $teacher->getSpecialty(),
                    'is_active' => $teacher->isActive(),
                ], $teachers),
                'action_result' => 'success',
                'timestamp' => new DateTime(),
            ]);

            return $teachers;
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Teacher search failed - Database error', [
                'criteria' => $criteria,
                'error_type' => 'Doctrine_DBAL_Exception',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'action_result' => 'database_error',
                'timestamp' => new DateTime(),
            ]);

            throw new RuntimeException('Failed to search teachers due to database error');
        } catch (Exception $e) {
            $this->logger->critical('Teacher search failed - Unexpected error', [
                'criteria' => $criteria,
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'action_result' => 'unexpected_error',
                'timestamp' => new DateTime(),
            ]);

            throw new RuntimeException('Failed to search teachers due to unexpected error');
        }
    }

    /**
     * Get teacher by email.
     */
    public function findByEmail(string $email): ?Teacher
    {
        $this->logger->info('Starting teacher lookup by email', [
            'email' => $email,
            'email_length' => strlen($email),
            'email_domain' => substr($email, strpos($email, '@') + 1),
            'action' => 'find_teacher_by_email',
            'timestamp' => new DateTime(),
        ]);

        try {
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->logger->warning('Invalid email format provided for teacher lookup', [
                    'email' => $email,
                    'validation_result' => 'invalid_format',
                    'action_result' => 'validation_error',
                ]);

                return null;
            }

            $this->logger->debug('Executing teacher lookup query', [
                'email' => $email,
                'repository_method' => 'findByEmail',
            ]);

            $teacher = $this->teacherRepository->findByEmail($email);

            if ($teacher) {
                $this->logger->info('Teacher found successfully by email', [
                    'email' => $email,
                    'teacher_id' => $teacher->getId(),
                    'teacher_name' => $teacher->getFirstName() . ' ' . $teacher->getLastName(),
                    'teacher_specialty' => $teacher->getSpecialty(),
                    'teacher_active' => $teacher->isActive(),
                    'teacher_verified' => $teacher->isEmailVerified(),
                    'created_at' => $teacher->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'action_result' => 'found',
                    'timestamp' => new DateTime(),
                ]);
            } else {
                $this->logger->info('No teacher found with provided email', [
                    'email' => $email,
                    'email_domain' => substr($email, strpos($email, '@') + 1),
                    'action_result' => 'not_found',
                    'timestamp' => new DateTime(),
                ]);
            }

            return $teacher;
        } catch (\Doctrine\DBAL\Exception $e) {
            $this->logger->error('Teacher lookup by email failed - Database error', [
                'email' => $email,
                'error_type' => 'Doctrine_DBAL_Exception',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'action_result' => 'database_error',
                'timestamp' => new DateTime(),
            ]);

            throw new RuntimeException('Failed to lookup teacher due to database error');
        } catch (Exception $e) {
            $this->logger->critical('Teacher lookup by email failed - Unexpected error', [
                'email' => $email,
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
                'action_result' => 'unexpected_error',
                'timestamp' => new DateTime(),
            ]);

            throw new RuntimeException('Failed to lookup teacher due to unexpected error');
        }
    }

    /**
     * Check if email exists.
     */
    public function emailExists(string $email): bool
    {
        $this->logger->info('Starting email existence check for teacher', [
            'email' => $email,
            'email_length' => strlen($email),
            'email_domain' => substr($email, strpos($email, '@') + 1),
            'action' => 'check_email_exists',
            'timestamp' => new DateTime(),
        ]);

        try {
            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->logger->warning('Invalid email format provided for existence check', [
                    'email' => $email,
                    'validation_result' => 'invalid_format',
                    'action_result' => 'validation_error',
                ]);

                return false;
            }

            $this->logger->debug('Executing email existence lookup', [
                'email' => $email,
                'lookup_method' => 'findByEmail',
            ]);

            $teacher = $this->findByEmail($email);
            $exists = $teacher !== null;

            $this->logger->info('Email existence check completed', [
                'email' => $email,
                'email_domain' => substr($email, strpos($email, '@') + 1),
                'exists' => $exists,
                'teacher_id' => $exists ? $teacher->getId() : null,
                'teacher_name' => $exists ? $teacher->getFirstName() . ' ' . $teacher->getLastName() : null,
                'teacher_active' => $exists ? $teacher->isActive() : null,
                'action_result' => $exists ? 'exists' : 'not_exists',
                'timestamp' => new DateTime(),
            ]);

            return $exists;
        } catch (Exception $e) {
            $this->logger->error('Email existence check failed - Error during lookup', [
                'email' => $email,
                'error_type' => get_class($e),
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'action_result' => 'lookup_error',
                'timestamp' => new DateTime(),
            ]);

            // Return false on error to prevent blocking operations
            return false;
        }
    }
}
