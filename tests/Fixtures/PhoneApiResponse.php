<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Tests\Fixtures;

class PhoneApiResponse
{
    /**
     * Generate a valid phone API response for testing
     * Based on: https://www.ipqualityscore.com/documentation/phone-number-validation-api/overview
     */
    public static function success(array $overrides = []): array
    {
        return array_merge([
            'success' => true,
            'message' => 'Success.',
            'valid' => true,
            'active' => true,
            'fraud_score' => 10,
            'recent_abuse' => false,
            'VOIP' => false,
            'prepaid' => false,
            'risky' => false,
            'carrier' => 'AT&T',
            'line_type' => 'Wireless',
            'country' => 'US',
            'region' => 'California',
            'city' => 'Los Angeles',
            'timezone' => 'America/Los_Angeles',
            'zip_code' => '90001',
            'dialing_code' => 1,
            'do_not_call' => false,
            'leaked' => false,
            'spammer' => false,
            'active_status' => 'active',
            'formatted' => '+1 (555) 123-4567',
            'local_format' => '(555) 123-4567',
            'request_id' => 'PHONE123',
        ], $overrides);
    }

    /**
     * VOIP/prepaid high-risk phone response
     */
    public static function highRisk(array $overrides = []): array
    {
        return self::success(array_merge([
            'fraud_score' => 88,
            'VOIP' => true,
            'prepaid' => true,
            'risky' => true,
            'recent_abuse' => true,
            'line_type' => 'VOIP',
            'carrier' => 'Unknown VOIP',
        ], $overrides));
    }

    /**
     * Invalid phone number response
     */
    public static function invalid(array $overrides = []): array
    {
        return self::success(array_merge([
            'valid' => false,
            'active' => false,
            'fraud_score' => 100,
        ], $overrides));
    }

    /**
     * Rate limit exceeded response
     */
    public static function rateLimited(): array
    {
        return [
            'success' => false,
            'message' => 'Rate limit exceeded. Please try again later.',
            'request_id' => bin2hex(random_bytes(16)),
        ];
    }

    /**
     * Request timeout response
     */
    public static function timeout(): array
    {
        return [
            'success' => false,
            'message' => 'Request timeout - the request took too long to complete',
            'request_id' => bin2hex(random_bytes(16)),
        ];
    }

    /**
     * Malformed JSON response for testing error handling
     */
    public static function malformedJson(): string
    {
        return '{invalid json: missing closing brace and quotes';
    }
}
