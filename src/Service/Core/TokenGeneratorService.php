<?php

namespace App\Service\Core;

use Symfony\Component\Uid\Uuid;

/**
 * Token Generator Service
 * 
 * Handles the generation of secure tokens for needs analysis requests.
 * Provides UUID-based tokens with configurable expiration periods.
 */
class TokenGeneratorService
{
    /**
     * Default token expiration in days
     */
    private const DEFAULT_EXPIRATION_DAYS = 30;

    /**
     * Generate a secure UUID token
     */
    public function generateToken(): string
    {
        return Uuid::v4()->toRfc4122();
    }

    /**
     * Generate an expiration date for a token
     */
    public function generateExpirationDate(int $days = self::DEFAULT_EXPIRATION_DAYS): \DateTimeImmutable
    {
        return new \DateTimeImmutable("+{$days} days");
    }

    /**
     * Check if a token is expired
     */
    public function isTokenExpired(\DateTimeImmutable $expirationDate): bool
    {
        return $expirationDate < new \DateTimeImmutable();
    }

    /**
     * Get remaining days before token expiration
     */
    public function getRemainingDays(\DateTimeImmutable $expirationDate): int
    {
        $now = new \DateTimeImmutable();
        
        if ($expirationDate < $now) {
            return 0;
        }

        $interval = $now->diff($expirationDate);
        return $interval->days;
    }

    /**
     * Check if token is expiring soon (within specified days)
     */
    public function isExpiringSoon(\DateTimeImmutable $expirationDate, int $warningDays = 7): bool
    {
        $remainingDays = $this->getRemainingDays($expirationDate);
        return $remainingDays > 0 && $remainingDays <= $warningDays;
    }

    /**
     * Generate a token with custom expiration
     */
    public function generateTokenWithExpiration(int $days = self::DEFAULT_EXPIRATION_DAYS): array
    {
        return [
            'token' => $this->generateToken(),
            'expires_at' => $this->generateExpirationDate($days)
        ];
    }

    /**
     * Validate token format (UUID v4)
     */
    public function isValidTokenFormat(string $token): bool
    {
        try {
            $uuid = Uuid::fromString($token);
            return $uuid->toRfc4122() === $token;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Get default expiration days
     */
    public function getDefaultExpirationDays(): int
    {
        return self::DEFAULT_EXPIRATION_DAYS;
    }

    /**
     * Calculate expiration percentage (0-100)
     * 0 = just created, 100 = expired
     */
    public function getExpirationPercentage(\DateTimeImmutable $createdAt, \DateTimeImmutable $expiresAt): float
    {
        $now = new \DateTimeImmutable();
        
        // If expired, return 100%
        if ($now >= $expiresAt) {
            return 100.0;
        }
        
        // If not yet created (edge case), return 0%
        if ($now <= $createdAt) {
            return 0.0;
        }
        
        $totalDuration = $createdAt->diff($expiresAt)->days;
        $elapsedDuration = $createdAt->diff($now)->days;
        
        if ($totalDuration === 0) {
            return 0.0;
        }
        
        return min(100.0, ($elapsedDuration / $totalDuration) * 100);
    }

    /**
     * Get expiration status with color coding for UI
     */
    public function getExpirationStatus(\DateTimeImmutable $expiresAt): array
    {
        $remainingDays = $this->getRemainingDays($expiresAt);
        
        if ($remainingDays === 0) {
            return [
                'status' => 'expired',
                'label' => 'Expiré',
                'color' => 'danger',
                'days' => 0
            ];
        }
        
        if ($remainingDays <= 3) {
            return [
                'status' => 'critical',
                'label' => 'Expire bientôt',
                'color' => 'danger',
                'days' => $remainingDays
            ];
        }
        
        if ($remainingDays <= 7) {
            return [
                'status' => 'warning',
                'label' => 'Expire prochainement',
                'color' => 'warning',
                'days' => $remainingDays
            ];
        }
        
        return [
            'status' => 'valid',
            'label' => 'Valide',
            'color' => 'success',
            'days' => $remainingDays
        ];
    }

    /**
     * Generate multiple unique tokens
     */
    public function generateMultipleTokens(int $count): array
    {
        $tokens = [];
        
        for ($i = 0; $i < $count; $i++) {
            $tokens[] = $this->generateToken();
        }
        
        return array_unique($tokens);
    }

    /**
     * Create a short token for display purposes (first 8 characters)
     */
    public function getShortToken(string $token): string
    {
        if (!$this->isValidTokenFormat($token)) {
            return 'Invalid';
        }
        
        return substr($token, 0, 8) . '...';
    }
}