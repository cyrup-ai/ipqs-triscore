<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Enum;

/**
 * Abuse velocity/frequency from IPQS IP API
 *
 * Indicates how frequently the IP has been associated with abusive behavior
 *
 * API returns lowercase strings (e.g., "high", "medium", "low", "none")
 */
enum AbuseVelocity: string
{
    case NONE = 'none';
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';

    /**
     * Parse from API response string
     *
     * @param string|null $value API response value (lowercase)
     * @return self|null Enum case or null if unparseable
     */
    public static function fromString(?string $value): ?self
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        // API returns lowercase, so normalize
        return self::tryFrom(strtolower($value));
    }
}
