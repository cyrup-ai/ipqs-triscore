<?php
declare(strict_types=1);

namespace Kodegen\Ipqs\Tests\Fixtures;

class IpApiResponse
{
    /**
     * Generate a valid IP API response for testing
     * Based on: https://www.ipqualityscore.com/documentation/proxy-detection-api/overview
     */
    public static function success(array $overrides = []): array
    {
        return array_merge([
            'success' => true,
            'message' => 'Success.',
            'fraud_score' => 25,
            'country_code' => 'US',
            'region' => 'Texas',
            'city' => 'Houston',
            'ISP' => 'Mediacom Cable',
            'ASN' => 30036,
            'organization' => 'Mediacom Cable',
            'latitude' => 29.7079,
            'longitude' => -95.401,
            'is_crawler' => false,
            'timezone' => 'America/Chicago',
            'mobile' => false,
            'host' => '192-0-2-110.client.mchsi.com',
            'proxy' => false,
            'vpn' => false,
            'tor' => false,
            'active_vpn' => false,
            'active_tor' => false,
            'recent_abuse' => false,
            'bot_status' => false,
            'connection_type' => 'Residential',
            'abuse_velocity' => 'none',
            'zip_code' => '77001',
            'request_id' => '0w8WYS',
        ], $overrides);
    }

    /**
     * High fraud score IP response (VPN + proxy + abuse)
     */
    public static function highFraud(array $overrides = []): array
    {
        return self::success(array_merge([
            'fraud_score' => 95,
            'proxy' => true,
            'vpn' => true,
            'recent_abuse' => true,
            'abuse_velocity' => 'high',
            'bot_status' => true,
            'connection_type' => 'Corporate',
        ], $overrides));
    }

    /**
     * Medium fraud score IP response
     */
    public static function mediumFraud(array $overrides = []): array
    {
        return self::success(array_merge([
            'fraud_score' => 65,
            'vpn' => true,
            'connection_type' => 'Corporate',
        ], $overrides));
    }

    /**
     * API error response
     */
    public static function error(string $message = 'Invalid API key'): array
    {
        return [
            'success' => false,
            'message' => $message,
            'request_id' => 'ERR123',
        ];
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
