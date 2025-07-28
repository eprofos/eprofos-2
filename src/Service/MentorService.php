<?php

namespace App\Service;

use App\Entity\User\Mentor;
use App\Repository\User\MentorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Mentor Service
 * 
 * Handles business logic for mentor management including email notifications,
 * password reset functionality, invitation system, and data export capabilities.
 */
class MentorService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MentorRepository $mentorRepository,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
        private string $fromEmail = 'noreply@eprofos.fr',
        private string $fromName = 'EPROFOS - École Professionnelle de Formation Spécialisée',
        private string $adminEmail = 'admin@eprofos.fr'
    ) {
    }

    /**
     * Send welcome email to new mentor
     */
    public function sendWelcomeEmail(Mentor $mentor, ?string $plainPassword = null): bool
    {
        try {
            $this->logger->info('Sending welcome email to mentor', [
                'mentor_id' => $mentor->getId(),
                'email' => $mentor->getEmail()
            ]);

            // Generate login URL
            $loginUrl = $this->urlGenerator->generate(
                'mentor_login',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($mentor->getEmail(), $mentor->getFullName()))
                ->subject('Bienvenue sur EPROFOS - Votre compte mentor')
                ->htmlTemplate('emails/mentor_welcome.html.twig')
                ->context([
                    'mentor' => $mentor,
                    'login_url' => $loginUrl,
                    'plain_password' => $plainPassword,
                    'has_password' => $plainPassword !== null
                ]);

            $this->mailer->send($email);

            $this->logger->info('Welcome email sent successfully', [
                'mentor_id' => $mentor->getId()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send welcome email', [
                'mentor_id' => $mentor->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send invitation email to potential mentor
     */
    public function sendInvitationEmail(string $email, string $companyName, array $invitationData = []): bool
    {
        try {
            $this->logger->info('Sending invitation email to potential mentor', [
                'email' => $email,
                'company' => $companyName
            ]);

            // Generate registration URL
            $registrationUrl = $this->urlGenerator->generate(
                'mentor_register',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($email))
                ->subject('Invitation à rejoindre EPROFOS en tant que mentor entreprise')
                ->htmlTemplate('emails/mentor_invitation.html.twig')
                ->context([
                    'company_name' => $companyName,
                    'registration_url' => $registrationUrl,
                    'invitation_data' => $invitationData
                ]);

            $this->mailer->send($email);

            $this->logger->info('Invitation email sent successfully', [
                'email' => $email
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send invitation email', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send password reset email to mentor
     */
    public function sendPasswordResetEmail(Mentor $mentor): bool
    {
        try {
            $this->logger->info('Sending password reset email to mentor', [
                'mentor_id' => $mentor->getId(),
                'email' => $mentor->getEmail()
            ]);

            // Generate reset token
            $resetToken = $mentor->generatePasswordResetToken();
            $this->entityManager->flush();

            // Generate reset URL
            $resetUrl = $this->urlGenerator->generate(
                'mentor_reset_password',
                ['token' => $resetToken],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($mentor->getEmail(), $mentor->getFullName()))
                ->subject('Réinitialisation de votre mot de passe EPROFOS')
                ->htmlTemplate('emails/mentor_password_reset.html.twig')
                ->context([
                    'mentor' => $mentor,
                    'reset_url' => $resetUrl,
                    'expires_at' => $mentor->getPasswordResetTokenExpiresAt()
                ]);

            $this->mailer->send($email);

            $this->logger->info('Password reset email sent successfully', [
                'mentor_id' => $mentor->getId()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send password reset email', [
                'mentor_id' => $mentor->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send email verification link to mentor
     */
    public function sendEmailVerification(Mentor $mentor): bool
    {
        try {
            $this->logger->info('Sending email verification to mentor', [
                'mentor_id' => $mentor->getId(),
                'email' => $mentor->getEmail()
            ]);

            // Generate verification token if not exists
            if (!$mentor->getEmailVerificationToken()) {
                $mentor->generateEmailVerificationToken();
                $this->entityManager->flush();
            }

            // Generate verification URL
            $verificationUrl = $this->urlGenerator->generate(
                'mentor_verify_email',
                ['token' => $mentor->getEmailVerificationToken()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($mentor->getEmail(), $mentor->getFullName()))
                ->subject('Vérification de votre adresse email EPROFOS')
                ->htmlTemplate('emails/mentor_email_verification.html.twig')
                ->context([
                    'mentor' => $mentor,
                    'verification_url' => $verificationUrl
                ]);

            $this->mailer->send($email);

            $this->logger->info('Email verification sent successfully', [
                'mentor_id' => $mentor->getId()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send email verification', [
                'mentor_id' => $mentor->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Send new password email to mentor
     */
    public function sendNewPasswordEmail(Mentor $mentor, string $newPassword): bool
    {
        try {
            $this->logger->info('Sending new password email to mentor', [
                'mentor_id' => $mentor->getId(),
                'email' => $mentor->getEmail()
            ]);

            // Generate login URL
            $loginUrl = $this->urlGenerator->generate(
                'mentor_login',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($mentor->getEmail(), $mentor->getFullName()))
                ->subject('Nouveau mot de passe EPROFOS')
                ->htmlTemplate('emails/mentor_new_password.html.twig')
                ->context([
                    'mentor' => $mentor,
                    'new_password' => $newPassword,
                    'login_url' => $loginUrl
                ]);

            $this->mailer->send($email);

            $this->logger->info('New password email sent successfully', [
                'mentor_id' => $mentor->getId()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send new password email', [
                'mentor_id' => $mentor->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Generate random password
     */
    public function generateRandomPassword(int $length = 12): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        return $password;
    }

    /**
     * Export mentors data to CSV format
     */
    public function exportToCsv(array $mentors): string
    {
        $csvData = [];
        
        // Headers
        $csvData[] = [
            'ID',
            'Email',
            'Prénom',
            'Nom',
            'Téléphone',
            'Poste',
            'Entreprise',
            'SIRET',
            'Domaines d\'expertise',
            'Années d\'expérience',
            'Niveau de formation',
            'Actif',
            'Email vérifié',
            'Date de création',
            'Dernière connexion'
        ];

        // Data rows
        foreach ($mentors as $mentor) {
            $csvData[] = [
                $mentor->getId(),
                $mentor->getEmail(),
                $mentor->getFirstName(),
                $mentor->getLastName(),
                $mentor->getPhone() ?: '',
                $mentor->getPosition(),
                $mentor->getCompanyName(),
                $mentor->getCompanySiret(),
                implode(', ', $mentor->getExpertiseDomainsLabels()),
                $mentor->getExperienceYears(),
                $mentor->getEducationLevelLabel(),
                $mentor->isActive() ? 'Oui' : 'Non',
                $mentor->isEmailVerified() ? 'Oui' : 'Non',
                $mentor->getCreatedAt()->format('Y-m-d H:i:s'),
                $mentor->getLastLoginAt() ? $mentor->getLastLoginAt()->format('Y-m-d H:i:s') : ''
            ];
        }

        // Convert to CSV string
        $output = fopen('php://temp', 'r+');
        foreach ($csvData as $row) {
            fputcsv($output, $row, ';');
        }
        rewind($output);
        $csvString = stream_get_contents($output);
        fclose($output);

        return $csvString;
    }

    /**
     * Get dashboard statistics
     */
    public function getDashboardStatistics(): array
    {
        $statistics = $this->mentorRepository->getStatistics();
        
        // Add additional calculated statistics
        $statistics['recent_registrations'] = $this->mentorRepository->findRecentlyRegistered(30);
        $statistics['unverified_emails'] = $this->mentorRepository->countUnverifiedEmails();
        $statistics['inactive_mentors'] = $this->mentorRepository->countInactive();
        $statistics['experience_stats'] = $this->mentorRepository->getExperienceStatistics();
        $statistics['education_distribution'] = $this->mentorRepository->getEducationLevelDistribution();
        
        return $statistics;
    }

    /**
     * Clean up expired password reset tokens
     */
    public function cleanupExpiredTokens(): int
    {
        $this->logger->info('Cleaning up expired password reset tokens');
        
        $qb = $this->entityManager->createQueryBuilder();
        $qb->update(Mentor::class, 'm')
           ->set('m.passwordResetToken', 'NULL')
           ->set('m.passwordResetTokenExpiresAt', 'NULL')
           ->where('m.passwordResetTokenExpiresAt < :now')
           ->setParameter('now', new \DateTimeImmutable());
        
        $affected = $qb->getQuery()->execute();
        
        $this->logger->info('Expired password reset tokens cleaned up', [
            'affected_count' => $affected
        ]);
        
        return $affected;
    }

    /**
     * Send admin notification for new mentor registration
     */
    public function sendAdminNotificationForNewMentor(Mentor $mentor): bool
    {
        try {
            $this->logger->info('Sending admin notification for new mentor', [
                'mentor_id' => $mentor->getId()
            ]);

            $adminUrl = $this->urlGenerator->generate(
                'admin_mentor_show',
                ['id' => $mentor->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($this->adminEmail, 'Administration EPROFOS'))
                ->subject('Nouveau compte mentor créé - EPROFOS')
                ->htmlTemplate('emails/admin_new_mentor_notification.html.twig')
                ->context([
                    'mentor' => $mentor,
                    'admin_url' => $adminUrl
                ]);

            $this->mailer->send($email);

            $this->logger->info('Admin notification sent successfully', [
                'mentor_id' => $mentor->getId()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send admin notification', [
                'mentor_id' => $mentor->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Validate mentor data
     */
    public function validateMentorData(Mentor $mentor): array
    {
        $errors = [];

        // Check email uniqueness
        $existingMentor = $this->mentorRepository->findByEmail($mentor->getEmail());
        if ($existingMentor && $existingMentor->getId() !== $mentor->getId()) {
            $errors['email'] = 'Cette adresse email est déjà utilisée.';
        }

        // Validate email format
        if (!filter_var($mentor->getEmail(), FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Format d\'email invalide.';
        }

        // Check SIRET uniqueness and format
        if ($mentor->getCompanySiret()) {
            $existingSiret = $this->mentorRepository->findByCompanySiret($mentor->getCompanySiret());
            if ($existingSiret && $existingSiret->getId() !== $mentor->getId()) {
                $errors['companySiret'] = 'Ce SIRET est déjà utilisé par un autre mentor.';
            }

            if (!preg_match('/^\d{14}$/', $mentor->getCompanySiret())) {
                $errors['companySiret'] = 'Le SIRET doit contenir exactement 14 chiffres.';
            }
        }

        // Validate required fields
        if (empty($mentor->getFirstName())) {
            $errors['firstName'] = 'Le prénom est obligatoire.';
        }

        if (empty($mentor->getLastName())) {
            $errors['lastName'] = 'Le nom est obligatoire.';
        }

        if (empty($mentor->getPosition())) {
            $errors['position'] = 'Le poste est obligatoire.';
        }

        if (empty($mentor->getCompanyName())) {
            $errors['companyName'] = 'Le nom de l\'entreprise est obligatoire.';
        }

        if (empty($mentor->getExpertiseDomains())) {
            $errors['expertiseDomains'] = 'Au moins un domaine d\'expertise doit être sélectionné.';
        }

        if ($mentor->getExperienceYears() === null || $mentor->getExperienceYears() < 0) {
            $errors['experienceYears'] = 'L\'expérience en années est obligatoire.';
        }

        if (empty($mentor->getEducationLevel())) {
            $errors['educationLevel'] = 'Le niveau de formation est obligatoire.';
        }

        return $errors;
    }

    /**
     * Find available mentors for apprentice matching
     */
    public function findAvailableMentorsForMatching(array $criteria = [], int $maxApprenticesPerMentor = 3): array
    {
        // For now, return active mentors since Alternant entity doesn't exist yet
        $mentors = $this->mentorRepository->findActive();

        // Filter by criteria if provided
        if (!empty($criteria['expertise_domains'])) {
            $mentors = array_filter($mentors, function (Mentor $mentor) use ($criteria) {
                foreach ($criteria['expertise_domains'] as $domain) {
                    if ($mentor->hasExpertiseDomain($domain)) {
                        return true;
                    }
                }
                return false;
            });
        }

        if (!empty($criteria['min_experience'])) {
            $mentors = array_filter($mentors, function (Mentor $mentor) use ($criteria) {
                return $mentor->getExperienceYears() >= $criteria['min_experience'];
            });
        }

        if (!empty($criteria['education_level'])) {
            $mentors = array_filter($mentors, function (Mentor $mentor) use ($criteria) {
                return $mentor->getEducationLevel() === $criteria['education_level'];
            });
        }

        return array_values($mentors);
    }

    /**
     * Get mentor statistics for specific company
     */
    public function getCompanyStatistics(string $companyName): array
    {
        $mentors = $this->mentorRepository->findByCompanyName($companyName);
        
        return [
            'total_mentors' => count($mentors),
            'active_mentors' => count(array_filter($mentors, fn($m) => $m->isActive())),
            'verified_mentors' => count(array_filter($mentors, fn($m) => $m->isEmailVerified())),
            'average_experience' => count($mentors) > 0 ? 
                array_sum(array_map(fn($m) => $m->getExperienceYears(), $mentors)) / count($mentors) : 0,
            'expertise_domains' => $this->getCompanyExpertiseDomains($mentors),
        ];
    }

    /**
     * Get expertise domains for a company's mentors
     */
    private function getCompanyExpertiseDomains(array $mentors): array
    {
        $domains = [];
        foreach ($mentors as $mentor) {
            foreach ($mentor->getExpertiseDomains() as $domain) {
                if (!isset($domains[$domain])) {
                    $domains[$domain] = 0;
                }
                $domains[$domain]++;
            }
        }
        
        arsort($domains);
        return $domains;
    }

    /**
     * Check if mentor can supervise new apprentice
     */
    public function canSuperviseNewApprentice(Mentor $mentor, int $maxApprenticesPerMentor = 3): bool
    {
        // TODO: Implement when Alternant entity is created
        // For now, always return true since we can't count current apprentices
        return $mentor->isActive() && $mentor->isEmailVerified();
    }

    /**
     * Send apprentice assignment notification to mentor
     */
    public function sendApprenticeAssignmentNotification(Mentor $mentor, array $apprenticeData): bool
    {
        try {
            $this->logger->info('Sending apprentice assignment notification to mentor', [
                'mentor_id' => $mentor->getId()
            ]);

            $dashboardUrl = $this->urlGenerator->generate(
                'mentor_dashboard',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($mentor->getEmail(), $mentor->getFullName()))
                ->subject('Nouvel alternant assigné - EPROFOS')
                ->htmlTemplate('emails/mentor_apprentice_assignment.html.twig')
                ->context([
                    'mentor' => $mentor,
                    'apprentice_data' => $apprenticeData,
                    'dashboard_url' => $dashboardUrl
                ]);

            $this->mailer->send($email);

            $this->logger->info('Apprentice assignment notification sent successfully', [
                'mentor_id' => $mentor->getId()
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to send apprentice assignment notification', [
                'mentor_id' => $mentor->getId(),
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Set configuration parameters
     */
    public function setEmailConfig(string $fromEmail, string $fromName, string $adminEmail): void
    {
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
        $this->adminEmail = $adminEmail;
    }

    /**
     * Calculate performance metrics for a mentor
     */
    public function calculatePerformanceMetrics(Mentor $mentor): array
    {
        // Placeholder implementation until we have proper relationships
        return [
            'alternants_count' => 0,
            'active_contracts' => 0,
            'missions_count' => 0,
            'evaluations_count' => 0,
            'satisfaction_rate' => 0,
            'success_rate' => 0,
        ];
    }

    /**
     * Get recent activity for a mentor
     */
    public function getRecentActivity(Mentor $mentor): array
    {
        // Placeholder implementation - return empty array for now
        return [];
    }

    /**
     * Get detailed performance metrics for a mentor over a period
     */
    public function getDetailedPerformance(Mentor $mentor, int $months): array
    {
        // Placeholder implementation - return minimal structure
        return [
            'alternants_count' => 0,
            'success_rate' => 0,
            'satisfaction_rate' => 0,
            'evaluations_count' => 0,
        ];
    }

    /**
     * Export mentors to various formats
     */
    public function exportMentors(array $mentors, string $format): string
    {
        switch ($format) {
            case 'csv':
                return $this->exportToCsv($mentors);
            case 'xlsx':
                return $this->exportToExcel($mentors);
            default:
                throw new \InvalidArgumentException("Unsupported export format: {$format}");
        }
    }

    /**
     * Export mentors to Excel format
     */
    private function exportToExcel(array $mentors): string
    {
        // For now, return CSV format as Excel is not yet implemented
        return $this->exportToCsv($mentors);
    }
}
