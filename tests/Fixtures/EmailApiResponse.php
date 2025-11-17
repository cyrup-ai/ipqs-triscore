<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Tests\Fixtures;

class EmailApiResponse
{
    /**
     * Generate a valid email API response for testing
     * Based on: https://www.ipqualityscore.com/documentation/email-validation-api/overview
     */
    public static function success(array $overrides = []): array
    {
        return array_merge([
            'success' => true,
            'valid' => true,
            'disposable' => false,
            'smtp_score' => 3,
            'overall_score' => 4,
            'first_name' => 'John',
            'generic' => false,
            'common' => false,
            'dns_valid' => true,
            'honeypot' => false,
            'deliverability' => 'high',
            'frequent_complainer' => false,
            'spam_trap_score' => 'none',
            'catch_all' => false,
            'timed_out' => false,
            'suspect' => false,
            'recent_abuse' => false,
            'fraud_score' => 15,
            'leaked' => false,
            'domain_velocity' => 'none',
            'domain_trust' => 'high',
            'user_activity' => 'high',
            'sanitized_email' => 'john@example.com',
            'request_id' => 'EMAIL123',
        ], $overrides);
    }

    /**
     * Disposable/temporary email response
     */
    public static function disposable(array $overrides = []): array
    {
        return self::success(array_merge([
            'valid' => true,
            'disposable' => true,
            'fraud_score' => 75,
            'smtp_score' => 2,
            'overall_score' => 1,
            'deliverability' => 'low',
            'suspect' => true,
        ], $overrides));
    }

    /**
     * Invalid email response
     */
    public static function invalid(array $overrides = []): array
    {
        return self::success(array_merge([
            'valid' => false,
            'smtp_score' => -1,
            'overall_score' => 0,
            'deliverability' => 'low',
            'fraud_score' => 85,
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
