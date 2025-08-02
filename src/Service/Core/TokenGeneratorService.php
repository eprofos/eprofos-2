<?php

declare(strict_types=1);

namespace App\Service\Core;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Token Generator Service.
 *
 * Handles the generation of secure tokens for needs analysis requests.
 * Provides UUID-based tokens with configurable expiration periods.
 */
class TokenGeneratorService
{
    /**
     * Default token expiration in days.
     */
    private const DEFAULT_EXPIRATION_DAYS = 30;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Generate a secure UUID token.
     */
    public function generateToken(): string
    {
        try {
            $this->logger->info('Starting token generation');

            $token = Uuid::v4()->toRfc4122();

            $this->logger->info('Token generated successfully', [
                'token_length' => strlen($token),
                'token_format' => 'uuid_v4_rfc4122',
                'short_token' => substr($token, 0, 8) . '...',
            ]);

            return $token;
        } catch (Exception $e) {
            $this->logger->error('Failed to generate token', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new InvalidArgumentException('Unable to generate secure token: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Generate an expiration date for a token.
     */
    public function generateExpirationDate(int $days = self::DEFAULT_EXPIRATION_DAYS): DateTimeImmutable
    {
        try {
            $this->logger->info('Generating expiration date', [
                'expiration_days' => $days,
                'default_days' => self::DEFAULT_EXPIRATION_DAYS,
            ]);

            if ($days <= 0) {
                $this->logger->warning('Invalid expiration days provided, using default', [
                    'provided_days' => $days,
                    'default_days' => self::DEFAULT_EXPIRATION_DAYS,
                ]);
                $days = self::DEFAULT_EXPIRATION_DAYS;
            }

            $expirationDate = new DateTimeImmutable("+{$days} days");

            $this->logger->info('Expiration date generated successfully', [
                'expiration_date' => $expirationDate->format('Y-m-d H:i:s'),
                'days_from_now' => $days,
                'timezone' => $expirationDate->getTimezone()->getName(),
            ]);

            return $expirationDate;
        } catch (Exception $e) {
            $this->logger->error('Failed to generate expiration date', [
                'days' => $days,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new InvalidArgumentException('Unable to generate expiration date: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if a token is expired.
     */
    public function isTokenExpired(DateTimeImmutable $expirationDate): bool
    {
        try {
            $this->logger->debug('Checking token expiration', [
                'expiration_date' => $expirationDate->format('Y-m-d H:i:s'),
                'current_time' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            $isExpired = $expirationDate < new DateTimeImmutable();

            $this->logger->debug('Token expiration check completed', [
                'is_expired' => $isExpired,
                'expiration_date' => $expirationDate->format('Y-m-d H:i:s'),
            ]);

            return $isExpired;
        } catch (Exception $e) {
            $this->logger->error('Error checking token expiration', [
                'expiration_date' => $expirationDate->format('Y-m-d H:i:s'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // In case of error, consider token as expired for security
            return true;
        }
    }

    /**
     * Get remaining days before token expiration.
     */
    public function getRemainingDays(DateTimeImmutable $expirationDate): int
    {
        try {
            $this->logger->debug('Calculating remaining days', [
                'expiration_date' => $expirationDate->format('Y-m-d H:i:s'),
            ]);

            $now = new DateTimeImmutable();

            if ($expirationDate < $now) {
                $this->logger->debug('Token already expired', [
                    'expiration_date' => $expirationDate->format('Y-m-d H:i:s'),
                    'current_time' => $now->format('Y-m-d H:i:s'),
                ]);

                return 0;
            }

            $interval = $now->diff($expirationDate);
            $remainingDays = $interval->days;

            $this->logger->debug('Remaining days calculated', [
                'remaining_days' => $remainingDays,
                'expiration_date' => $expirationDate->format('Y-m-d H:i:s'),
                'current_time' => $now->format('Y-m-d H:i:s'),
            ]);

            return $remainingDays;
        } catch (Exception $e) {
            $this->logger->error('Error calculating remaining days', [
                'expiration_date' => $expirationDate->format('Y-m-d H:i:s'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return 0 for security in case of error
            return 0;
        }
    }

    /**
     * Check if token is expiring soon (within specified days).
     */
    public function isExpiringSoon(DateTimeImmutable $expirationDate, int $warningDays = 7): bool
    {
        try {
            $this->logger->debug('Checking if token is expiring soon', [
                'expiration_date' => $expirationDate->format('Y-m-d H:i:s'),
                'warning_days' => $warningDays,
            ]);

            $remainingDays = $this->getRemainingDays($expirationDate);
            $isExpiringSoon = $remainingDays > 0 && $remainingDays <= $warningDays;

            $this->logger->debug('Token expiration soon check completed', [
                'remaining_days' => $remainingDays,
                'warning_days' => $warningDays,
                'is_expiring_soon' => $isExpiringSoon,
            ]);

            return $isExpiringSoon;
        } catch (Exception $e) {
            $this->logger->error('Error checking if token is expiring soon', [
                'expiration_date' => $expirationDate->format('Y-m-d H:i:s'),
                'warning_days' => $warningDays,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Consider as expiring soon for safety
            return true;
        }
    }

    /**
     * Generate a token with custom expiration.
     */
    public function generateTokenWithExpiration(int $days = self::DEFAULT_EXPIRATION_DAYS): array
    {
        try {
            $this->logger->info('Generating token with expiration', [
                'expiration_days' => $days,
            ]);

            $token = $this->generateToken();
            $expiresAt = $this->generateExpirationDate($days);

            $result = [
                'token' => $token,
                'expires_at' => $expiresAt,
            ];

            $this->logger->info('Token with expiration generated successfully', [
                'short_token' => substr($token, 0, 8) . '...',
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                'expiration_days' => $days,
            ]);

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Failed to generate token with expiration', [
                'expiration_days' => $days,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new InvalidArgumentException('Unable to generate token with expiration: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate token format (UUID v4).
     */
    public function isValidTokenFormat(string $token): bool
    {
        try {
            $this->logger->debug('Validating token format', [
                'token_length' => strlen($token),
                'short_token' => substr($token, 0, 8) . '...',
            ]);

            $uuid = Uuid::fromString($token);
            $isValid = $uuid->toRfc4122() === $token;

            $this->logger->debug('Token format validation completed', [
                'is_valid' => $isValid,
                'short_token' => substr($token, 0, 8) . '...',
                'expected_format' => 'uuid_v4_rfc4122',
            ]);

            return $isValid;
        } catch (InvalidArgumentException $e) {
            $this->logger->warning('Invalid token format detected', [
                'token_length' => strlen($token),
                'short_token' => substr($token, 0, 8) . '...',
                'error' => $e->getMessage(),
            ]);

            return false;
        } catch (Exception $e) {
            $this->logger->error('Unexpected error during token validation', [
                'token_length' => strlen($token),
                'short_token' => substr($token, 0, 8) . '...',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Get default expiration days.
     */
    public function getDefaultExpirationDays(): int
    {
        $this->logger->debug('Retrieving default expiration days', [
            'default_days' => self::DEFAULT_EXPIRATION_DAYS,
        ]);

        return self::DEFAULT_EXPIRATION_DAYS;
    }

    /**
     * Calculate expiration percentage (0-100)
     * 0 = just created, 100 = expired.
     */
    public function getExpirationPercentage(DateTimeImmutable $createdAt, DateTimeImmutable $expiresAt): float
    {
        try {
            $this->logger->debug('Calculating expiration percentage', [
                'created_at' => $createdAt->format('Y-m-d H:i:s'),
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            ]);

            $now = new DateTimeImmutable();

            // If expired, return 100%
            if ($now >= $expiresAt) {
                $this->logger->debug('Token expired, returning 100%', [
                    'current_time' => $now->format('Y-m-d H:i:s'),
                    'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                ]);

                return 100.0;
            }

            // If not yet created (edge case), return 0%
            if ($now <= $createdAt) {
                $this->logger->debug('Token not yet created, returning 0%', [
                    'current_time' => $now->format('Y-m-d H:i:s'),
                    'created_at' => $createdAt->format('Y-m-d H:i:s'),
                ]);

                return 0.0;
            }

            $totalDuration = $createdAt->diff($expiresAt)->days;
            $elapsedDuration = $createdAt->diff($now)->days;

            if ($totalDuration === 0) {
                $this->logger->warning('Zero total duration detected', [
                    'created_at' => $createdAt->format('Y-m-d H:i:s'),
                    'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                ]);

                return 0.0;
            }

            $percentage = min(100.0, ($elapsedDuration / $totalDuration) * 100);

            $this->logger->debug('Expiration percentage calculated', [
                'percentage' => $percentage,
                'total_duration_days' => $totalDuration,
                'elapsed_duration_days' => $elapsedDuration,
            ]);

            return $percentage;
        } catch (Exception $e) {
            $this->logger->error('Error calculating expiration percentage', [
                'created_at' => $createdAt->format('Y-m-d H:i:s'),
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return 100% for safety in case of error
            return 100.0;
        }
    }

    /**
     * Get expiration status with color coding for UI.
     */
    public function getExpirationStatus(DateTimeImmutable $expiresAt): array
    {
        try {
            $this->logger->debug('Getting expiration status', [
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            ]);

            $remainingDays = $this->getRemainingDays($expiresAt);

            if ($remainingDays === 0) {
                $status = [
                    'status' => 'expired',
                    'label' => 'Expiré',
                    'color' => 'danger',
                    'days' => 0,
                ];
            } elseif ($remainingDays <= 3) {
                $status = [
                    'status' => 'critical',
                    'label' => 'Expire bientôt',
                    'color' => 'danger',
                    'days' => $remainingDays,
                ];
            } elseif ($remainingDays <= 7) {
                $status = [
                    'status' => 'warning',
                    'label' => 'Expire prochainement',
                    'color' => 'warning',
                    'days' => $remainingDays,
                ];
            } else {
                $status = [
                    'status' => 'valid',
                    'label' => 'Valide',
                    'color' => 'success',
                    'days' => $remainingDays,
                ];
            }

            $this->logger->debug('Expiration status determined', [
                'status' => $status['status'],
                'remaining_days' => $remainingDays,
                'color' => $status['color'],
            ]);

            return $status;
        } catch (Exception $e) {
            $this->logger->error('Error getting expiration status', [
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return expired status for safety
            return [
                'status' => 'expired',
                'label' => 'Erreur',
                'color' => 'danger',
                'days' => 0,
            ];
        }
    }

    /**
     * Generate multiple unique tokens.
     */
    public function generateMultipleTokens(int $count): array
    {
        try {
            $this->logger->info('Generating multiple tokens', [
                'requested_count' => $count,
            ]);

            if ($count <= 0) {
                $this->logger->warning('Invalid token count requested', [
                    'requested_count' => $count,
                ]);

                throw new InvalidArgumentException('Token count must be greater than 0');
            }

            if ($count > 1000) {
                $this->logger->warning('Large token count requested', [
                    'requested_count' => $count,
                    'max_recommended' => 1000,
                ]);
            }

            $tokens = [];
            $generated = 0;
            $attempts = 0;
            $maxAttempts = $count * 2; // Safety limit

            while ($generated < $count && $attempts < $maxAttempts) {
                $token = $this->generateToken();

                if (!in_array($token, $tokens, true)) {
                    $tokens[] = $token;
                    $generated++;
                } else {
                    $this->logger->warning('Duplicate token generated, retrying', [
                        'attempt' => $attempts,
                        'generated_count' => $generated,
                        'short_token' => substr($token, 0, 8) . '...',
                    ]);
                }

                $attempts++;
            }

            if ($generated < $count) {
                $this->logger->error('Failed to generate required number of unique tokens', [
                    'requested_count' => $count,
                    'generated_count' => $generated,
                    'attempts' => $attempts,
                ]);

                throw new InvalidArgumentException("Could not generate {$count} unique tokens after {$attempts} attempts");
            }

            $this->logger->info('Multiple tokens generated successfully', [
                'requested_count' => $count,
                'generated_count' => $generated,
                'attempts' => $attempts,
                'uniqueness_verified' => count(array_unique($tokens)) === $count,
            ]);

            return $tokens;
        } catch (Exception $e) {
            $this->logger->error('Failed to generate multiple tokens', [
                'requested_count' => $count,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new InvalidArgumentException('Unable to generate multiple tokens: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Create a short token for display purposes (first 8 characters).
     */
    public function getShortToken(string $token): string
    {
        try {
            $this->logger->debug('Creating short token for display', [
                'original_length' => strlen($token),
            ]);

            if (!$this->isValidTokenFormat($token)) {
                $this->logger->warning('Invalid token format for short token creation', [
                    'token_length' => strlen($token),
                    'partial_token' => substr($token, 0, 16) . '...',
                ]);

                return 'Invalid';
            }

            $shortToken = substr($token, 0, 8) . '...';

            $this->logger->debug('Short token created successfully', [
                'short_token' => $shortToken,
                'original_length' => strlen($token),
            ]);

            return $shortToken;
        } catch (Exception $e) {
            $this->logger->error('Error creating short token', [
                'token_length' => strlen($token),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 'Error';
        }
    }
}
