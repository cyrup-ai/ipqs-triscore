<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Enum;

/**
 * Network connection type from IPQS IP API
 *
 * Higher risk types: Data Center
 * Lower risk types: Residential, Corporate, Education
 *
 * API returns title-case strings (e.g., "Data Center")
 */
enum ConnectionType: string
{
    case RESIDENTIAL = 'Residential';
    case CORPORATE = 'Corporate';
    case EDUCATION = 'Education';
    case MOBILE = 'Mobile';
    case DATA_CENTER = 'Data Center';

    /**
     * Parse from API response string (handles case variations)
     *
     * @param string|null $value API response value
     * @return self|null Enum case or null if unparseable
     */
    public static function fromString(?string $value): ?self
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        // Try exact match first
        $result = self::tryFrom($value);
        if ($result !== null) {
            return $result;
        }

        // Handle case variations and underscores
        return match(strtoupper($value)) {
            'RESIDENTIAL' => self::RESIDENTIAL,
            'CORPORATE' => self::CORPORATE,
            'EDUCATION' => self::EDUCATION,
            'MOBILE' => self::MOBILE,
            'DATA CENTER', 'DATA_CENTER', 'DATACENTER' => self::DATA_CENTER,
            default => null,
        };
    }
}
